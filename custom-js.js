$(document).ready(function(){
    $('.button').click(function(e){
        e.preventDefault();
        var button =  $(this);
        var clickBtnValue = $(this).val();
        var formNameVal = $("#name").val();
        var formTagLineVal = $("#tagLine").val();
        button.attr("disabled", true);
        var ajaxurl = 'api.php',
        data =  {
            'action': clickBtnValue,
            'name': formNameVal,
            'tagLine': formTagLineVal
        };
        $('#summonerInfo').html("<img src='teemoLoad.gif'/><h3>Loading...</h3>");
        setTimeout(() => {
            $.post(ajaxurl, data, function (response) {
                button.attr("disabled", false);
                console.log(response);  
                $('#summonerInfo').html(response);
            });
        }, 500);
    });
});