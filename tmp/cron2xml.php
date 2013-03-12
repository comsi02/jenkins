<?php


function cron2xml()
{
    $jenkins_base = '/home/comsi02/work/jenkins';
    $jenkins_url  = 'http://10.2.8.101:9100';

    $cmd = "java -jar $jenkins_base/home/war/WEB-INF/jenkins-cli.jar -s $jenkins_url list-jobs --username comsi02 --password fsdfsd";
    $cmd = "java -jar $jenkins_base/home/war/WEB-INF/jenkins-cli.jar -s $jenkins_url list-jobs";

    exec($cmd,$result,$retval);

    if($retval == 0) {
        echo "----------------------------------------\n";
        var_dump($result);
        echo "----------------------------------------\n";
    } else {
        print "cron2xml.php error!!!\n";
        die(1);
    }

    #$fh = fopen("$jenkins_base/cron/bbat.tmonc.net.cron","r");
    #var_dump($fh);

    $cron_file_info = file_get_contents("$jenkins_base/cron/bbat.tmonc.net.cron",true);

    $ss = explode("\r\n",$cron_file_info); 
    foreach ($ss as $s)
    {
        var_dump($s);
    }




}

cron2xml();
?>
