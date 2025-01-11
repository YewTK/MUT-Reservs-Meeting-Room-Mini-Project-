$(document).ready(function() {
    $("#search-id").keyup(function() {
        let search_id = $(this).val();
        if (search_id != "") {
            $.ajax({
                url: "search.php",
                method: "post",
                data: {
                    query: search_id
                },
                success: function(response) {
                    $("#show-id").html(response);
                }
            });
        } else {
            $("#show-id").html("");
        }
    });
    $(document).on('click','a', function(){
        $('#search-id').val($(this).text());
        $("#show-id").html("");
    });
});
