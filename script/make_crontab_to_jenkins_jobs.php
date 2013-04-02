<?php

function usage() { /*{{{*/

    if(is_array($_SERVER['argv']) and $_SERVER['argc'] < 2 ) {
        print ( "\n" );
        print ( "\tphp {$_SERVER['argv'][0]} [OPTIONS] \n" );
        print ( "\n" );
        print ( "Options are:\n" );
        print ( "\t-s, --site   <SITE_URL>\tconnect to jenkins at site.\tdefault [http://127.0.0.1:9100]\n" );
        print ( "\t-j, --jar    <JAR_FILE>\tjenkins-cli.jar file.\t\tdefault [../lib/jenkins-cli.jar]\n" );
        print ( "\t-c, --cron   <CRONTAB>\tcrontab file or directory.\n" );
        print ( "\t-u, --userid <USER_ID>\tjenkins user id. \n" );
        print ( "\t-p, --passwd\t\tjenkins user password. \n" );
        print ( "\t-a, --access\t\taccess password file. \n" );
        print ( "\t-e, --encode\t\tcreate encode password file. \n" );
        print ( "\n" );
        print ( "Usage:\n" );
        print ( "\tphp {$_SERVER['argv'][0]} -s http://localhost:9100 -j ../lib/jenkins-cli.jar -c ./crontab/bbat.tmonc.net.cron -u comsi02 -p\n" );
        print ( "\tphp {$_SERVER['argv'][0]} -j ../lib/jenkins-cli.jar -c ./crontab/ -u comsi02 -p\n" );
        print ( "\tphp {$_SERVER['argv'][0]} -c ./crontab/ \n" );
        print ( "\n" );

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

    for ( $i=0; $i<count($_SERVER['argv']); $i++ )
    {
        $arg = $_SERVER['argv'][$i];

        $params['site'] = 'http://127.0.0.1:9100';
        $params['jar']  = '../lib/jenkins-cli.jar';
        $params['userid']  = 'jenkins';
        $params['passwd']  = "dGpxbHRtMTIzIQ==";

        if      ( $arg=="-s" || $arg=="--site" )     $params['site']   = $_SERVER['argv'][++$i];
        else if ( $arg=="-j" || $arg=="--jar" )      $params['jar']    = $_SERVER['argv'][++$i];
        else if ( $arg=="-c" || $arg=="--cron" )     $params['cron']   = $_SERVER['argv'][++$i];
        else if ( $arg=="-u" || $arg=="--userid" )   $params['userid'] = $_SERVER['argv'][++$i];
        else if ( $arg=="-a" || $arg=="--access" )   $params['passwd'] = file_get_contents(trim($_SERVER['argv'][++$i]));
        else if ( $arg=="-p" || $arg=="--passwd" ) {
            echo "password: ";
            system('stty -echo');
            $params['passwd'] = base64_encode(trim(fgets(STDIN)));
            system('stty echo');
            echo "\n";
        }
    }

    $jenkins_cmd  = "/bin/env java -jar {$params['jar']} -s {$params['site']} ";
    $module = array('job','cmd','sms','eml','ssh');
    $cron_info = array();

    #----------------------------------------------------------------------------#
    # Jenkins Cli Login
    #----------------------------------------------------------------------------#
    if ($params['userid'] and $params['passwd']) {

        $passwd_file = base64_decode($params['passwd']);

        $res = jenkins_cli_exec("$jenkins_cmd login --username {$params['userid']} --password $passwd_file\n");

        if ($res[0] == true) {
            echo "[SUCC] Jenkins Cli Login completed...\n";
        } else {
            echo "[SUCC] Jenkins Cli Login failure...\n";
            exit(-1);
        }
    }

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
    $params['cron_file'] = array();

    if (is_dir($params['cron'])) {
        $dh = opendir($params['cron']);

        while($file = readdir($dh)) {
            if ($file != '.' && $file != '..') {
                $params['cron_file'][] = $params['cron'].'/'.$file;
            }
        }
    } elseif (file_exists($params['cron'])) {
        $params['cron_file'][] = $params['cron'];
    }
    
    print_r($params);

    if (!empty($params['cron_file'])) {

        foreach ($params['cron_file'] as $crontab_file) {
            $cron_cont = explode("\n",file_get_contents($crontab_file,true)); 
            foreach ($cron_cont as $line) {
                foreach ($module as $m) {
                    $pattern = '/^\# '.$m.'\|/';
		            if (preg_match($pattern,$line)) {
			            $res = explode("|",$line,3);
			            $cron_info[$m][$res[1]] = $res[2];
		            }
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

        $job_ssh = $cron_info['ssh'][$job_name];

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

        if (is_null($job_ssh)) {
            $builders = "
    <hudson.tasks.Shell>
      <command>$job_cmd</command>
    </hudson.tasks.Shell>";
        } else {
            $builders = "
    <org.jvnet.hudson.plugins.SSHBuilder plugin=\"ssh@2.3\">
      <siteName>$job_ssh</siteName>
      <command>$job_cmd</command>
    </org.jvnet.hudson.plugins.SSHBuilder>";
        }

        $job_templete = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<project>
  <actions/>
  <description>$job_desc</description>
  <logRotator class=\"hudson.tasks.LogRotator\">
    <daysToKeep>7</daysToKeep>
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
$builders
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

        print_r(array($job_name,$job_desc,$job_status,str_replace("\n"," ",$job_schedule),$job_cmd,$job_noti_eml,$job_ssh));

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
