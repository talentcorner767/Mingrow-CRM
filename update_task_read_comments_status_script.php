<script>
    $("body").on("click", ".unread-comments-of-tasks, .unread-comments-of-kanban", function () {
        var id = $(this).attr("data-id");
        if (!id) {
            id = $(this).attr("data-post-id");
        }

        appAjaxRequest({url: '<?php echo get_uri("tasks/set_task_comments_as_read") ?>/' + id});

        $(this).removeClass("unread-comments-of-kanban").removeClass("unread");
        $(this).removeClass("unread-comments-of-tasks");
        $(this).find("svg.unread-comments-of-tasks-icon").remove();
    });
</script>