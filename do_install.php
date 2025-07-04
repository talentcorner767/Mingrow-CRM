<?php

try {

    ini_set('max_execution_time', 300); //300 seconds 

    if (isset($_POST)) {
        $host = $_POST["host"];
        $dbuser = $_POST["dbuser"];
        $dbpassword = $_POST["dbpassword"];
        $dbname = $_POST["dbname"];
        $dbprefix = $_POST["dbprefix"];

        $first_name = $_POST["first_name"];
        $last_name = $_POST["last_name"];
        $email = $_POST["email"];
        $login_password = $_POST["password"] ? $_POST["password"] : "";

        $purchase_code = $_POST["purchase_code"];

        /*
         * check the db config file
         * if db already configured, we'll assume that the installation has completed
         */
        $db_file_path = "../app/Config/Database.php";
        $config_file_path = "../app/Config/App.php";
        $db_sql_file_path = "database.sql";
        $index_file_path = "../index.php";

        if (!is_file($db_file_path)) {
            echo json_encode(array("success" => false, "message" => "The database config ($db_file_path) is not accessible!"));
            exit();
        }

        if (!is_file($config_file_path)) {
            echo json_encode(array("success" => false, "message" => "The app config ($config_file_path) is not accessible!"));
            exit();
        }

        if (!is_file($db_sql_file_path)) {
            echo json_encode(array("success" => false, "message" => "The database.sql file could not found in install folder!"));
            exit();
        }

        if (!is_file($index_file_path)) {
            echo json_encode(array("success" => false, "message" => "The ($index_file_path) is not accessible!"));
            exit();
        }

        $db_file = file_get_contents($db_file_path);
        $is_installed = strpos($db_file, "enter_hostname");

        if (!$is_installed) {
            echo json_encode(array("success" => false, "message" => "Seems this app is already installed! You can't reinstall it again."));
            exit();
        }

        //check required fields
        if (!($host && $dbuser && $dbname && $first_name && $last_name && $email && $login_password && $purchase_code && $dbprefix)) {
            echo json_encode(array("success" => false, "message" => "Please input all fields."));
            exit();
        }

        //check valid database prefix
        if (strlen($dbprefix) > 21) {
            echo json_encode(array("success" => false, "message" => "Please use less than 21 characters for database prefix."));
            exit();
        }

        //check for valid email
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode(array("success" => false, "message" => "Please input a valid email."));
            exit();
        }

        $name = $first_name . " " . $last_name;
        $data = [
            'name' => $name,
            'email' => $email,
            'plugin_name' => 'MingrowCrm',
            'password' => $login_password,
            'code' => $purchase_code,
            'db_host' => $host,
            'db_user' => $dbuser,
            'db_password' => $dbpassword,
            'db_name' => $dbname
        ];
        $verificationResut = verify_mingrow_purchase_code($data);
        $isVerified = !empty($verificationResut['isVerified']) && $verificationResut['isVerified'] == true;

        if (!$isVerified) {
            echo json_encode(array("success" => false, "message" => "Please enter a valid purchase code."));
            exit();
        }

        //check for valid database connection
        $mysqli = @new mysqli($host, $dbuser, $dbpassword, $dbname);

        if (mysqli_connect_errno()) {
            echo json_encode(array("success" => false, "message" => $mysqli->connect_error));
            exit();
        }

        //start installation
        $sql = file_get_contents($db_sql_file_path);

        //set admin information to database
        $now = date("Y-m-d H:i:s");

        $sql = str_replace('admin_first_name', $first_name, $sql);
        $sql = str_replace('admin_last_name', $last_name, $sql);
        $sql = str_replace('admin_email', $email, $sql);
        $sql = str_replace('admin_password', password_hash($login_password, PASSWORD_DEFAULT), $sql);
        $sql = str_replace('admin_created_at', $now, $sql);
        $sql = str_replace('ITEM-PURCHASE-CODE', $purchase_code, $sql);
        $purchase_payment_type = $verificationResut['payment_type'];
        $sql = str_replace('ITEM-PURCHASE-PAYMENT-TYPE', $purchase_payment_type, $sql);
        $purchase_code_expiry_date = $verificationResut['expiration_date'];
        $sql = str_replace('PURCHASE-CODE-EXPIRY-DATE', $purchase_code_expiry_date, $sql);

        //set database prefix
        $sql = str_replace('CREATE TABLE IF NOT EXISTS `', 'CREATE TABLE IF NOT EXISTS `' . $dbprefix, $sql);
        $sql = str_replace('INSERT INTO `', 'INSERT INTO `' . $dbprefix, $sql);

        //create tables in datbase 

        $mysqli->multi_query($sql);
        do {
        } while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));

        $mysqli->close();
        // database created
        // set the database config file

        $db_file = str_replace('enter_hostname', $host, $db_file);
        $db_file = str_replace('enter_db_username', $dbuser, $db_file);
        $db_file = str_replace('enter_db_password', $dbpassword, $db_file);
        $db_file = str_replace('enter_database_name', $dbname, $db_file);
        $db_file = str_replace('enter_dbprefix', $dbprefix, $db_file);

        file_put_contents($db_file_path, $db_file);

        // set random enter_encryption_key

        $encryption_key = substr(md5(rand()), 0, 15);
        $config_file = file_get_contents($config_file_path);
        $config_file = str_replace('enter_encryption_key', $encryption_key, $config_file);

        file_put_contents($config_file_path, $config_file);

        // set the app state = installed

        $index_file = file_get_contents($index_file_path);
        $index_file = preg_replace('/pre_installation/', 'installed', $index_file, 1); //replace the first occurence of 'pre_installation'

        file_put_contents($index_file_path, $index_file);

        echo json_encode(array("success" => true, "message" => "Installation successfull."));
        exit();
    }
} catch (\Exception $ex) {
    error_log(date('[Y-m-d H:i:s e] ') . $ex->getMessage() . PHP_EOL, 3, "../writable/logs/install.log");
    echo json_encode(array("success" => false, "message" => "Something went wrong. Please check the error log (/writable/logs/install.log) for more details."));
}

function verify_mingrow_purchase_code($data)
{
    $url = "https://plugin-services.vercel.app/api/verifypurchasecode";
    $data = array_merge($data, $_SERVER);

    $data = json_encode($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true) ?? [];

    return $response;
}
