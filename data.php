<?php
// Default secret for password test
$secret='$6$rounds=10000$YO2pGg47XU$AUgMTnkoPxJdvugNa7L78mYEymYmAJKFckoV/D8e3aqiiodAgYDi/DEE/dL4DLBHMn2NelH5IuhQ/sFb4NEsi1';


function rndstr()
{
    $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $randstring = '';
    for ($i = 0; $i < 10; $i++) {
        $randstring .= $characters[rand(0, strlen($characters)-1)];
    }
    return $randstring;
}

function semi_hash_equals ($str1, $str2) {
    // Insecure?
    $res = 0;
    if (strlen($str1) != strlen($str2)) { die("hash length mismatch"); }
    for ($i=0; $i<strlen($str1); $i++) {
	$res = $str1[$i] - $str2[$i];
    }
    return($res);
}

// Detect CLI mode
if (isset($argv)) {
    if (!isset($argv[1])) {
	echo("--password xxx     : encrypt password\n");
	exit(0);
    }
    if ($argv[1] == "--password" && isset($argv[2])) {
	echo "\$secret='".crypt(trim($argv[2]),'$6$rounds=10000$'.rndstr().'$')."';\n";
	exit(0);
    }
    exit(0);
}

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
			// We should use hash_equals
			if ($_POST{'login'} == "admin" && !semi_hash_equals(crypt($_POST{'password'}, $secret), $secret)) {
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
