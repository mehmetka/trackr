$(document).ready(function () {
    $(".collapseSidebar").on("click", function (e) {
        if ($(".vertical").hasClass("narrow")) {
            $(".vertical").toggleClass("open")
            $('#highlight-favorites').toggle(); // TODO doesnt work
        } else {
            $('#highlight-favorites').hide();
            ($(".vertical").toggleClass("collapsed"), $(".vertical").hasClass("hover") && $(".vertical").removeClass("hover")), e.preventDefault()
        }
    });

    var resultNavbarInfos = ajaxCall('GET', "/navbar-infos", null, false, false);
    $('#navbarTodayInfo').text(resultNavbarInfos.today);
    $('#navbarReadingAverageInfo').text(resultNavbarInfos.readingAverage);

});