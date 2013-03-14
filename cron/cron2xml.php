<?php

function usage() { /*{{{*/

    if($_SERVER['argc'] < 2 ) {
        echo "Usage:\n";
        echo "\tphp {$_SERVER['argv'][0]} <jenkins_home> <jenkins_port> <crontab file>\n";
        echo "\tphp {$_SERVER['argv'][0]} /home/comsi02/work/jenkins 9100 bbat.tmonc.net.cron\n";
        echo "\n";
        exit(-1);
    }
} /*}}}*/

function jenkins_cli_exec($jenkins_cmd) { /*{{{*/

    $resmsg = array();

    exec($jenkins_cmd,$result,$retval);

    if($retval != 0) {
        $resmsg[0] = false;
        $resmsg[1] = "cron2xml.php error!!!\n";
    } else {
        $resmsg[0] = true;
        $resmsg[1] = $result;
    }

    return $resmsg;
} /*}}}*/

function cron2xml() { /* {{{ */
    $jenkins_base = $_SERVER['argv'][1];
    $jenkins_url  = "http://127.0.0.1:{$_SERVER['argv'][2]}";
    $jenkins_cmd  = "/usr/bin/java -jar $jenkins_base/home/war/WEB-INF/jenkins-cli.jar -s $jenkins_url ";
    $crontab_file = "$jenkins_base/cron/{$_SERVER['argv'][3]}";

    $module = array('job','cmd','sms','eml');
    $cron_info = array();

    #----------------------------------------------------------------------------#
    # Jenkins 에 현재 등록된 job 목록을 가져와서 hash 에 기록.
    #----------------------------------------------------------------------------#
    $res = jenkins_cli_exec($jenkins_cmd.'list-jobs');

    if ($res[0] == true) {
        foreach ($res[1] as $job_name) {
            $cron_info['jenkins_list'][$job_name] = 'AV';
        }
        echo "[SUCC] Get Jenkins job list completed...\n";
    } else {
        echo "[FAIL] Get Jenkins job list failure...\n";
        exit(-1);
    }

    #----------------------------------------------------------------------------#
    # Crontab 에 등록된 Jenkins Job 을 parsing 해서 hash 에 기록.
    #----------------------------------------------------------------------------#
    if (file_exists($crontab_file) == true) {
        $cron_cont = explode("\r\n",file_get_contents($crontab_file,true)); 

        foreach ($cron_cont as $line) {
            foreach ($module as $m) {
                $pattern = '/^\# '.$m.'\|/';
		        if (preg_match($pattern,$line)) {
			        $res = explode("|",$line,3);
			        $cron_info[$m][$res[1]] = $res[2];
		        }
            }
        }
        echo "[SUCC] Crontab file read completed...\n";
    } else {
        echo "[FAIL] Crontab file not exist...\n";
        exit(-1);
    }

    #----------------------------------------------------------------------------#
    # Jenkins Job XML 을 만들기 위한 설정
    #----------------------------------------------------------------------------#
    foreach ($cron_info['job'] as $job_name => $job_info) {

        list($job_status, $job_desc) = explode("|",$cron_info['job'][$job_name],2);
        list($job_schedule, list($job_cmd)) = array_chunk((preg_split ("/\s+/", $cron_info['cmd'][$job_name],6)),5);

        $job_schedule = implode(" ",$job_schedule)."\n# $job_desc";

        $job_noti_eml = str_replace(";"," ",$cron_info['eml'][$job_name]);

        if ($job_noti_eml == null) {
            $job_noti_eml = str_replace(";"," ",$cron_info['eml']['default']);
        }

        $job_noti_sms = str_replace(";"," ",$cron_info['sms'][$job_name]);

        if ($job_noti_sms == null) {
            $job_noti_sms = str_replace(";"," ",$cron_info['sms']['default']);
        }

        if ($job_status == 'AV') {
            $job_status = 'false';
        } else {
            $job_status = 'true';
        }

        $jenkins_cli_cmd = 'create-job';
        if ($cron_info['jenkins_list'][$job_name] == 'AV') {
            $jenkins_cli_cmd = 'update-job';
        }

        $job_templete = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<project>
  <actions/>
  <description>$job_desc</description>
  <logRotator class=\"hudson.tasks.LogRotator\">
    <daysToKeep>1</daysToKeep>
    <numToKeep>-1</numToKeep>
    <artifactDaysToKeep>-1</artifactDaysToKeep>
    <artifactNumToKeep>-1</artifactNumToKeep>
  </logRotator>
  <keepDependencies>false</keepDependencies>
  <properties/>
  <scm class=\"hudson.scm.NullSCM\"/>
  <canRoam>true</canRoam>
  <disabled>$job_status</disabled>
  <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
  <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
  <triggers class=\"vector\">
    <hudson.triggers.TimerTrigger>
      <spec>$job_schedule</spec>
    </hudson.triggers.TimerTrigger>
  </triggers>
  <concurrentBuild>false</concurrentBuild>
  <builders>
    <hudson.tasks.Shell>
      <command>$job_cmd</command>
    </hudson.tasks.Shell>
  </builders>
  <publishers>
    <hudson.tasks.Mailer plugin=\"mailer@1.4\">
      <recipients>$job_noti_eml</recipients>
      <dontNotifyEveryUnstableBuild>false</dontNotifyEveryUnstableBuild>
      <sendToIndividuals>false</sendToIndividuals>
    </hudson.tasks.Mailer>
  </publishers>
  <buildWrappers/>
</project>";

        print "#--------------------[ $job_name ]--------------------#\n";

        $res = jenkins_cli_exec("$jenkins_cmd $jenkins_cli_cmd $job_name <<< '$job_templete'\n");

        print_r(array($job_name,$job_desc,$job_status,str_replace("\n"," ",$job_schedule),$job_cmd,$job_noti_eml));

        if ($res == true) {
            echo "[SUCC] : job create success.\n";
        } else {
            echo "[FAIL] : job create failure.\n";
        }
    }
} /* }}} */

usage();
cron2xml();
?>
