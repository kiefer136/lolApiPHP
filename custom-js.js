$(document).ready(function(){
    var ajaxurl1 = 'api.php', data1 =  {
        'action': 'createLeaderBoard',
        'type': 'POST'
    };
    $.post(ajaxurl1, data1, function (res) {
        console.log(res);  
        $('#leaderBoard').html(res);
    });
    $('.button').click(function(e){
        e.preventDefault();
        $('.teemo-dancing').removeClass('hidden');
        $('.main-page-div').addClass('blur-page');
        var clickBtnValue = $(this).val();
        var formNameVal = $("#name").val();
        var formTagLineVal = $("#tagLine").val();
        $('.button').attr("disabled", true);
        var ajaxurl = 'api.php',
        data =  {
            'action': clickBtnValue,
            'name': formNameVal,
            'tagLine': formTagLineVal,
            'type': 'POST'
        };
        setTimeout(() => {
            $.post(ajaxurl, data, function (response) {
                $('.teemo-dancing').addClass('hidden');
                $('.main-page-div').removeClass('blur-page');
                $('.button').attr("disabled", false);
                console.log(response);  
                $('#summonerInfo').html(response);
            });
        }, 500);
    });
});