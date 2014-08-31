<?php

function getsysint($path) {
	$data = "";
	$f = fopen($path, "r");
	if ($f) {
		$data = trim(fgets($f));
		fclose($f);
	}
	return($data);
}

function checkauth() {
	if (!isset($_SESSION{'authenticated'}) || $_SESSION{'authenticated'} != 1) {
		die("Security violation");
	}
}

function runcmd ($cmd) {
	$arr = array();
	$f = popen($cmd, "r");
	if ($f) {
		while(!feof($f)) {
			$arr{'output'} .= fread($f, 1024); 
		}
		pclose($f);
	}
	return($arr);
}

session_start();
header('Content-Type: application/json');
switch($_POST{'action'}) {
	case 'prelogin':
		$arr = array();
		$arr{'action'} = "prelogin"; //debug
		if (isset($_SESSION{'login'})) {
			$arr{'login'} = $_SESSION{'login'};
		}
		if (isset($_SESSION{'authenticated'})) {
			$arr{'authenticated'} = $_SESSION{'authenticated'};
		}
		echo json_encode($arr);
		break;
	case 'login':
		$arr = array();
		$arr{'status'} = "Login or password not set";
		if (isset($_POST{'login'}) && isset($_POST{'password'})) {
			// Normal auth
			if ($_POST{'login'} == "admin" && $_POST{'password'} == "admin") {
				$arr{'status'} = "OK";
				$_SESSION{'authenticated'} = 1;
				$_SESSION{'login'} = $_POST{'login'};
			} else {
				$arr{'status'} = "Login incorrect";
			}
		}
		echo json_encode($arr);
		break;
	case 'logout':
		$arr = array();
		unset($_SESSION{'authenticated'});
		echo json_encode($arr);
		break;

	case 'stat':
		checkauth();
		$arr = runcmd('accel-cmd show stat');
		echo json_encode($arr);
		break;

	case 'users':
		checkauth();
		$arr = runcmd('accel-cmd show sessions');
		$output2 = preg_replace('/\n/m', "</td></tr>\n<tr><td>",$arr{'output'});
		//$output2 = preg_replace('/^/m','<tr><td>',$output2);
		$output2 = preg_replace('/\|/m','</td><td>',$output2);
		$output2 = preg_replace('/<tr><td>$/','',$output2); // final
		$final .= '<table id="tusers" class="cell-border"><tbody><tr><td>' . $output2 . '</tbody></table>';
		$arr{'output'} = $final;
		echo json_encode($arr);
		break;

	case 'killsoft':
		checkauth();
		$arr = runcmd('accel-cmd terminate if '.escapeshellcmd(trim($_POST{'interface'}))." soft");
		echo json_encode($arr);
		break;

	case 'killhard':
		checkauth();
		$arr = runcmd('accel-cmd terminate if '.escapeshellcmd(trim($_POST{'interface'}))." hard");
		echo json_encode($arr);
		break;

	case 'ifstat':
		checkauth();
		$arr = array();
		$arr{'stamp'} = time()*1000;
		$arr{'rxbytes'} = intval(getsysint("/sys/class/net/".escapeshellcmd(trim($_POST{'interface'}))."/statistics/rx_bytes"));
		$arr{'txbytes'} = intval(getsysint("/sys/class/net/".escapeshellcmd(trim($_POST{'interface'}))."/statistics/tx_bytes"));
		$arr{'rxpackets'} = intval(getsysint("/sys/class/net/".escapeshellcmd(trim($_POST{'interface'}))."/statistics/rx_packets"));
		$arr{'txpackets'} = intval(getsysint("/sys/class/net/".escapeshellcmd(trim($_POST{'interface'}))."/statistics/tx_packets"));
		echo json_encode($arr);
		break;


	default:
		$arr{'status'} = "unknown command";
		echo json_encode($arr);
		break;
}


?>
