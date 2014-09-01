<!doctype html>
<html>
<head>
<title>accel-pppd</title>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.1/themes/cupertino/jquery-ui.css">
<link rel="stylesheet" href="//cdn.datatables.net/1.10.2/css/jquery.dataTables.css">
<link href="//medialize.github.io/jQuery-contextMenu/src/jquery.contextMenu.css" rel="stylesheet" type="text/css" />
<script src="//code.jquery.com/jquery-2.1.1.min.js"></script>
<script src="//code.jquery.com/ui/1.11.1/jquery-ui.min.js"></script>
<script src="//cdn.datatables.net/1.10.2/js/jquery.dataTables.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<style type="text/css">
body {
    font-size: 10px;
}
</style>
</head>
<body>
<div id="ifchart"></div>
<div id="loadingdialog" style="text-align:center">Please wait...</div>
<div id="logindialog">
<div id="message"></div>
<form id="flogin">
<input type=hidden name=action value="login">
<table>
<tr><td>Login:</td><td><input type="text" name="login" id="login"></td></tr>
<tr><td>Password:</td><td><input type="password" name="password" id="password"></td></tr>
</table>
</div>
<!-- MAIN SCREEN -->
<div id="mainscreen">
<ul>
    <li><a href="#tabmain">Main</a></li>
    <li><a href="#tabusers">Users</a></li>
    <li><a href="#tabsettings">Settings</a></li>
    <li><a href="#tablogout">Logout</a></li>
  </ul>
  <div id="tabmain">
  <input type=button id="autorefresh" value="Autorefresh: OFF">
  <pre></pre>
  </div>
  <div id="tabusers">
  </div>
  <div id="tabsettings">
  </div>
  <div id="tablogout">
  </div>
</div>
<!-- MAIN SCREEN EOF -->

<script>
var autorefresh = 0;
var oldtab = "tabmail";
var table = null;
var chart; // global
var chart_interface; // TODO: change this ugly global variable?

function requestData() {
    $.ajax({
        url: 'data.php',
	data: { action: "ifstat", interface: chart_interface },
	type: "POST",
        success: function(point) {
	    if (chart == null) {
		return;
	    }
            var series = chart.series[0],
                shift = series.data.length > 20; // shift if the series is 
                                                 // longer than 20
	    if ( typeof requestData.txbytes != 'undefined' ) {
		var stampdiff = point.stamp - requestData.stamp;
		var speedtx = (point.txbytes - requestData.txbytes) * 1000 * 8 / stampdiff;
		var speedrx = (point.rxbytes - requestData.rxbytes) * 1000 * 8 / stampdiff;
            	// add the point
            	chart.series[0].addPoint([point.stamp, speedtx], true, shift);
		chart.series[1].addPoint([point.stamp, speedrx], true, shift);
	    }
	    requestData.stamp = point.stamp;
            requestData.txbytes = point.txbytes;
	    requestData.rxbytes = point.rxbytes;
            
            // call it again after one second
            setTimeout(requestData, 1000);    
        },
        cache: false
    });
}

function showchart () {
	var winW = $(window).width() - 180;
	var winH = $(window).height() - 180;

	$("#ifchart").dialog({ height: winH, width: winW, modal: true,
		close: function( event, ui ) {
			delete requestData.txbytes;
			delete requestData.rxbytes;
			chart.destroy();
			chart = null;
		}
	 });
	chart = new Highcharts.Chart({

        chart: {
            renderTo: 'ifchart',
            defaultSeriesType: 'spline',
            events: {
                load: requestData
            }
        },
        title: {
            text: 'Interface statistics'
        },
        xAxis: {
            type: 'datetime',
            tickPixelInterval: 150,
            maxZoom: 20 * 1000
        },
        yAxis: {
            minPadding: 0.2,
            maxPadding: 0.2,
            title: {
                text: 'Bps',
                margin: 80
            }
        },
        series: [{
            name: 'TX Speed',
            data: []
        }, {
            name: 'RX Speed',
            data: []
        }]
    });
}


function activateselect() {
//	$(".act").selectmenu();
	// Deactivate, might make double fire
	$(".act").off();
	$(".act").change( function (e) {
		var username = $(this).closest("tr").children("td:nth-of-type(2)").text();
		var intf = $(this).closest("tr").children("td:nth-of-type(1)").text();
		var op = $(this).val();
		if (op == "watch") {
			$(".act").val('');
			chart_interface = intf;
			console.log('interface ' + intf);
			showchart();
		} else {
		   $("#loadingdialog").dialog('open');
		   $(".ui-dialog-titlebar").hide();
		   $.post("data.php", { action: op, interface: intf }, function (ret) {
			setTimeout(function() { 
				$(".act").val('');
				$("#loadingdialog").dialog('close');
			}, 1000);

		   });
		}
	});
}

function loadmain() {
        $.post("data.php", { action: "stat" }, function (ret) {
                $("#tabmain pre").html(ret.output);
        });
	if (autorefresh == 1) {
		setTimeout(loadmain, 1000);
	}
}

function activator (event, ui) {
	var tabname = ui.newPanel.attr('id');

	console.log('Activating tab ' + tabname);
	if (tabname == "tablogout") {
		$.post("data.php", { action: "logout" }, function (returned) {
			location.reload();
		});
	}
	if (oldtab == "tabusers") {
		table.clear();
		table.destroy();
		table = null;
		$("#tabusers").html('');
	}
	if (tabname == "tabmain") { loadmain(); }
	if (tabname == "tabusers") {
		$("#loadingdialog").dialog('open');
		$(".ui-dialog-titlebar").hide();
		$.post("data.php", { action: "users" }, function (ret) {
			//console.log(ret.output);
			$("#tabusers").html(ret.output);
			$("#tabusers").prepend('<button id="refreshusers">Refresh</button>');
			$("#refreshusers").button().click( function (e) {
				$("#loadingdialog").dialog('open');
				$(".ui-dialog-titlebar").hide();

				 $("#mainscreen").tabs("option", "active", 0);
				 setTimeout(function () { $("#mainscreen").tabs("option", "active", 1); }, 1000);
			});
			// Burp... ugly, but quick hack to remove +++---++ and so
			$("tr:eq(1)").remove();
			// Add action column
			// fix and add thead, datatables love it and need it
			$("#tusers").prepend($('<thead></thead>').append($('#tusers tr:first').remove()));
			// and also th for better styling
			$("tr:first-child td").each(function() {
				$(this).replaceWith('<th>' + $(this).text() + '</th>'); 
			});

			$("#tusers tr:eq(0)").append('<th>Operation</th>');
			$("#tusers tr:gt(0)").append('<td><select class="act" name="act"><option selected></option><option value="killhard">Terminate(hard)</option><option value="killsoft">Terminate(soft)</option><option value="watch">Watch live</option></select></td>');

			$("#tusers tr").click( function() { 
				// We can do something on row click... later.
				//var username = $(this).children("td:nth-of-type(2)").text();
			});

			
			table = $("#tusers").dataTable({ "iDisplayLength": 10 }).on('draw.dt', function (e, settings) {
				activateselect();
			});
			// Because draw is not fired on first event, we will fire manually
			activateselect();
			//$(".act").selectmenu();

			// Get user detailed info
			$("#loadingdialog").dialog('close');
		});
	}
}

function showmainscreen () {
	$("#mainscreen").show().tabs({
		activate: activator
	});
	loadmain();
	$("#autorefresh").button().click( function (ev) {
		ev.preventDefault();
		if (autorefresh == 0) {
			$("#autorefresh").prop('value', 'Autorefresh: ON');
			autorefresh = 1;
			setTimeout(loadmain, 1000);
		} else {
			$("#autorefresh").prop('value', 'Autorefresh: OFF');
			autorefresh = 0;
		}
	});
}


function trylogin(event) {
	event.preventDefault();
	var data = $('#flogin').serializeArray();
	$.post("data.php", data, function (returned) {
		if (returned.status == "OK") {
			$("#logindialog").dialog("close");
			showmainscreen();
		} else {
			$("#message").html(returned.status).css('color', 'red');
			$("#flogin").hide();
			// Remove message after while
			setTimeout(function(){ $("#message").html(''); $("#flogin").show(); }, 1000);
		}
	});
}

function prelogin(data) {
	if (data.authenticated == 1) {
		// We are already authenticated
		showmainscreen();
	} else {
		if (data.login) {
			$("#login").val(data.login);
		}
		$("#logindialog").dialog('open');
	}
}

$(document).ready(function(){
	$("#mainscreen").hide();
	$("#logindialog").dialog({ autoOpen: false, buttons: [ { text: "Login", click: trylogin } ], title: "accel-ppp login" });
	$("#loadingdialog").dialog({ autoOpen: false, modal: true });
	$.post( "data.php", { action: "prelogin" }, prelogin );
});
</script>
</body>
</html>
