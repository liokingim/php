<?php

class MyTask extends Thread
{
    private $m_inputs;
    private $m_outputs;


    public function __construct(array $inputs)
    {
        $this->m_inputs = $inputs;
        $this->m_outputs = new Thread(); // we will store the results in here.
    }


    public function run()
    {
        foreach ($this->m_inputs as $input)
        {
            // casting the array to an array is not a mistake
            // but actually super important for this to work
            // https://github.com/krakjoe/pthreads/issues/813#issuecomment-361955116
            $this->m_outputs[] = (array)array(
                'property1' => $input * 2,
                'property2' => ($input + 2),
            );
        }
    }


    # Accessors
    public function getResults() {
        return $this->m_outputs;
    }
}



function main()
{
    $inputs = range(0,10000);
    $numInputsPerTask = 20;
    $inputGroups = array_chunk($inputs, $numInputsPerTask);
    $numCpus = 4; // I would nomrally dynamically fetch this and sometimes large (e.g. aws C5 instances)
    $numTasks = count($inputGroups);
    $numThreads = min($numTasks, $numCpus); // don't need to spawn more threads than tasks.
    $pool = new Pool($numThreads);
    $tasks = array(); // collection to hold all the tasks to get the results from afterwards.

    foreach ($inputGroups as $inputsForTask)
    {
        $task = new MyTask($inputsForTask);
        $tasks[] = $task;
        $pool->submit($task);
    }


    while ($pool->collect());

    # We could submit more stuff here, the Pool is still waiting for work to be submitted.

    $pool->shutdown();

    # All tasks should have been completed at this point. Get the results!
    $results = array();
    foreach ($tasks as $task)
    {
        $results[] = $task->getResults();
    }

    print "results: " . print_r($results, true);
}

main();

// 비동기 결과값이 불필요한 경우
function curl_request_async($url, $params, $type='POST')
{
    foreach ($params as $key => &$val)
    {
        if (is_array($val))
		{
            $val = implode(',', $val);
		}
        $post_params[] = $key.'='.urlencode($val);
    }
    $post_string = implode('&', $post_params);

    $parts=parse_url($url);

    if ($parts['scheme'] == 'http')
    {
        $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port']:80, $errno, $errstr, 30);
    }
    else if ($parts['scheme'] == 'https')
    {
        $fp = fsockopen("ssl://" . $parts['host'], isset($parts['port']) ? $parts['port']:443, $errno, $errstr, 30);
    }

    // Data goes in the path for a GET request
    if('GET' == $type)
        $parts['path'] .= '?'.$post_string;

    $out = "$type ".$parts['path']." HTTP/1.1\r\n";
    $out.= "Host: ".$parts['host']."\r\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out.= "Content-Length: ".strlen($post_string)."\r\n";
    $out.= "Connection: Close\r\n\r\n";

    // Data goes in the request body for a POST request
    if ('POST' == $type && isset($post_string)) {
        $out.= $post_string;
    }

    // fwrite($fp, $out);

    while(!feof($fp)) {
        echo fgets($fp, 4096);
    }

    fclose($fp);
}

function getFromUrl($url, $method = 'GET')
{
    // Initialize
    $info   = parse_url($url);
    $req    = '';
    $data   = '';
    $line   = '';
    $agent  = 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.0; Trident/5.0)';
    $linebreak  = "\r\n";
    $headPassed = false;

    // Setting Protocol
    switch($info['scheme'] = strtoupper($info['scheme']))
    {
        case 'HTTP':
            $info['port']   = 80;
            break;

        case 'HTTPS':
            $info['ssl']    = 'ssl://';
            $info['port']   = 443;
            break;

        default:
            return false;
    }

    // Setting Path
    if(!$info['path'])
    {
        $info['path'] = '/';
    }

    // Setting Request Header
    switch($method = strtoupper($method))
    {
        case 'GET':
            if($info['query'])
            {
                $info['path'] .= '?' . $info['query'];
            }

            $req .= 'GET ' . $info['path'] . ' HTTP/1.1' . $linebreak;
            $req .= 'Host: ' . $info['host'] . $linebreak;
            $req .= 'User-Agent: ' . $agent . $linebreak;
            $req .= 'Referer: ' . $url . $linebreak;
            $req .= 'Connection: Close' . $linebreak . $linebreak;
            break;

        case 'POST':
            $req .= 'POST ' . $info['path'] . ' HTTP/1.1' . $linebreak;
            $req .= 'Host: ' . $info['host'] . $linebreak;
            $req .= 'User-Agent: ' . $agent . $linebreak;
            $req .= 'Referer: ' . $url . $linebreak;
            $req .= 'Content-Type: application/x-www-form-urlencoded'.$linebreak;
            $req .= 'Content-Length: '. strlen($info['query']) . $linebreak;
            $req .= 'Connection: Close' . $linebreak . $linebreak;
            $req .= $info['query'];
            break;
    }

    // Socket Open
    $fsock  = @fsockopen($info['ssl'] . $info['host'], $info['port']);
    if ($fsock)
    {
        fwrite($fsock, $req);
        while(!feof($fsock))
        {
            $line = fgets($fsock, 128);
            if($line == "\r\n" && !$headPassed)
            {
                $headPassed = true;
                continue;
            }
            if($headPassed)
            {
                $data .= $line;
            }
        }
        fclose($fsock);
    }

    return $data;
}

// posix 비동기
function curl_post_async($uri, $params)
{
        $command = "curl ";
        foreach ($params as $key => &$val)
                $command .= "-F '$key=$val' ";
        $command .= "$uri -s > /dev/null 2>&1 &";
        passthru($command);
}

// 동기
function https_post($uri, $postdata)
{
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Curl 을 이용한 방식
$post_data=array(
   'log_id'         => date('YmdHis'),
   'log_ip'         => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? ip2long($_SERVER['HTTP_X_FORWARDED_FOR']) : ip2long($_SERVER['REMOTE_ADDR']),
   'server_info'     => json_encode($_SERVER),
   'request_info'    => json_encode($_REQUEST),
   "cookie_info"     => json_encode($_COOKIE)
);

$url = 'http://brtech/logger.php';

$command = "curl ";
foreach ($post_data as $key => &$val)
   $command .= "-F '$key=$val' ";
$command .= "$uri -s > /dev/null 2>&1 &";
passthru($command);

// 자가호출 http://aaa.aa/main/set_log_with_json/data
$command = "php -f /home/brtech/public_html/index.php main/set_log_with_json/{$data} > /dev/null 2>&1 & ";
passthru($command);

// 네이티브 코드에 파라메터를 보낼 때에는
$command = "php -f /home/brtech/public_html/index.php main {$data} > /dev/null 2>&1 & ";
passthru($command);

print_r($argv);


// https://gist.githubusercontent.com/gcollazo/884a489a50aec7b53765405f40c6fbd1/raw/49d1568c34090587ac82e80612a9c350108b62c5/sample.json


