"use strict";
var base = {
    defaultFontFamily: "Overpass, sans-serif",
    primaryColor: "#1b68ff",
    secondaryColor: "#4f4f4f",
    successColor: "#3ad29f",
    warningColor: "#ffc107",
    infoColor: "#17a2b8",
    dangerColor: "#dc3545",
    darkColor: "#343a40",
    lightColor: "#f2f3f6"
};
var extend = {
    primaryColorLight: tinycolor(base.primaryColor).lighten(10).toString(),
    primaryColorLighter: tinycolor(base.primaryColor).lighten(30).toString(),
    primaryColorDark: tinycolor(base.primaryColor).darken(10).toString(),
    primaryColorDarker: tinycolor(base.primaryColor).darken(30).toString()
}
var chartColors = [base.primaryColor, base.successColor, "#6f42c1", extend.primaryColorLighter]
var colors = {
    bodyColor: "#6c757d",
    headingColor: "#495057",
    borderColor: "#e9ecef",
    backgroundColor: "#f8f9fa",
    mutedColor: "#adb5bd",
    chartTheme: "light"
}
var darkColor = {
    bodyColor: "#adb5bd",
    headingColor: "#e9ecef",
    borderColor: "#212529",
    backgroundColor: "#495057",
    mutedColor: "#adb5bd",
    chartTheme: "dark"
}
var curentTheme = "dark"
var dark = document.querySelector("#darkTheme")
colors = darkColor
dark.disabled = !1

var lineChartOptions = {
    series: [{
        name: "dummy",
        data: [31, 28, 30, 51, 42]
    }],
    chart: {height: 350, type: "line", background: !1, zoom: {enabled: !1}, toolbar: {show: !1}},
    theme: {mode: colors.chartTheme},
    stroke: {
        show: !0,
        curve: "smooth",
        lineCap: "round",
        colors: chartColors,
        width: [3, 2, 3],
        dashArray: [0, 0, 0]
    },
    dataLabels: {enabled: !1},
    responsive: [{breakpoint: 480, options: {legend: {position: "bottom", offsetX: -10, offsetY: 0}}}],
    markers: {
        size: 4,
        colors: base.primaryColor,
        strokeColors: colors.borderColor,
        strokeWidth: 2,
        strokeOpacity: .9,
        strokeDashArray: 0,
        fillOpacity: 1,
        discrete: [],
        shape: "circle",
        radius: 2,
        offsetX: 0,
        offsetY: 0,
        onClick: void 0,
        onDblClick: void 0,
        showNullDataPoints: !0,
        hover: {size: void 0, sizeOffset: 3}
    },
    xaxis: {
        type: "datetime",
        categories: ["12/11/2020 GMT", "12/12/2020 GMT", "12/13/2020 GMT", "12/14/2020 GMT", "12/15/2020 GMT"],
        labels: {
            show: !0,
            trim: !1,
            minHeight: void 0,
            maxHeight: 120,
            style: {colors: colors.mutedColor, cssClass: "text-muted", fontFamily: base.defaultFontFamily}
        },
        axisBorder: {show: !1}
    },
    yaxis: {
        labels: {
            show: !0,
            trim: !1,
            offsetX: -10,
            minHeight: void 0,
            maxHeight: 120,
            style: {colors: colors.mutedColor, cssClass: "text-muted", fontFamily: base.defaultFontFamily}
        }
    },
    legend: {
        position: "top",
        fontFamily: base.defaultFontFamily,
        fontWeight: 400,
        labels: {colors: colors.mutedColor, useSeriesColors: !1},
        markers: {
            width: 10,
            height: 10,
            strokeWidth: 0,
            strokeColor: colors.borderColor,
            fillColors: chartColors,
            radius: 6,
            customHTML: void 0,
            onClick: void 0,
            offsetX: 0,
            offsetY: 0
        },
        itemMargin: {horizontal: 10, vertical: 0},
        onItemClick: {toggleDataSeries: !0},
        onItemHover: {highlightDataSeries: !0}
    },
    grid: {
        show: !0,
        borderColor: colors.borderColor,
        strokeDashArray: 0,
        position: "back",
        xaxis: {lines: {show: !1}},
        yaxis: {lines: {show: !0}},
        row: {colors: void 0, opacity: .5},
        column: {colors: void 0, opacity: .5},
        padding: {top: 0, right: 0, bottom: 0, left: 0}
    }
};