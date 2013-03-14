jenkins
=======

jenkins deploy

# console 결과 보기
java -jar jenkins-cli.jar -s http://127.0.0.1:9100/ console jenkins_system_check

# job 설정 가져오기
java -jar jenkins-cli.jar -s http://127.0.0.1:9100/ get-job jenkins_system_check

# job 목록 가져오기
java -jar jenkins-cli.jar -s http://127.0.0.1:9100/ list-jobs

# job 생성하기
java -jar jenkins-cli.jar -s http://127.0.0.1:9100/ create-job job_name < job_name.xml

# job 업데이트
java -jar jenkins-cli.jar -s http://127.0.0.1:9100/ update-job job_name < job_name.xml

