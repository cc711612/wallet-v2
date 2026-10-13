// wallet CI/CD（usongrat Jenkins）
// 流程：composer install → 測試 → Slack 通知待核准 → 人工核准 → 透過受限 SSH 呼叫主機上的 deploy-app wallet

// 送 Slack 通知；commit message 會跳脫 & < >，避免注入 Slack 格式（例如 <!channel>）
def notifySlack(String kind) {
    node('php') {
        withCredentials([string(credentialsId: 'slack-webhook', variable: 'SLACK_URL')]) {
            withEnv(["KIND=${kind}", "RESULT=${currentBuild.currentResult}"]) {
                sh '''
                    php -r '
                        $esc = fn ($s) => str_replace(["&", "<", ">"], ["&amp;", "&lt;", "&gt;"], (string) $s);
                        $url = (string) getenv("BUILD_URL");
                        $title = getenv("JOB_NAME")." #".getenv("BUILD_NUMBER")." ".substr((string) getenv("DEPLOY_SHA"), 0, 7)." ".$esc(getenv("COMMIT_MSG"));
                        if (getenv("KIND") === "approval") {
                            $text = ":raised_hand: *等待部署核准* → ".getenv("PROD_HOST")."\n".$title."\n<".$url."input/|點此核准或拒絕>（30 分鐘內）";
                        } else {
                            $map = ["SUCCESS" => [":white_check_mark:", "部署成功"], "ABORTED" => [":double_vertical_bar:", "未部署（取消或核准逾時）"]];
                            [$icon, $word] = $map[getenv("RESULT")] ?? [":x:", "失敗"];
                            $text = $icon." ".$word." — ".$title." (<".$url."|build log>)";
                        }
                        echo json_encode(["text" => $text], JSON_UNESCAPED_UNICODE);
                    ' | curl -sf -X POST -H 'Content-Type: application/json' --data @- "$SLACK_URL" >/dev/null || true
                '''
            }
        }
    }
}

pipeline {
    agent none

    environment {
        APP         = 'wallet'
        PROD_HOST   = 'wallet.usongrat.tw'
        DEPLOY_CRED = 'wallet-deploy-ssh'
    }

    options {
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '20'))
    }

    stages {
        stage('Test') {
            agent { label 'php' }
            options { timeout(time: 15, unit: 'MINUTES') }
            environment {
                COMPOSER_CACHE_DIR = '/home/jenkins/agent/.composer-cache'
            }
            steps {
                script {
                    env.DEPLOY_SHA = sh(returnStdout: true, script: 'git rev-parse HEAD').trim()
                    env.COMMIT_MSG = sh(returnStdout: true, script: 'git log -1 --format=%s').trim()
                }
                sh 'composer install --no-interaction --prefer-dist --no-progress'
                sh '''
                    cp .env.example .env
                    php artisan key:generate --force
                    php artisan test
                '''
            }
            post {
                always { cleanWs() }
            }
        }

        stage('Approve') {
            // 先發 Slack 附核准連結；等待期間不佔用 agent，30 分鐘沒按就中止
            options { timeout(time: 30, unit: 'MINUTES') }
            steps {
                notifySlack('approval')
                input message: "部署 ${env.DEPLOY_SHA.take(7)}「${env.COMMIT_MSG}」到 ${env.PROD_HOST}？", ok: '部署'
            }
        }

        stage('Deploy') {
            agent { label 'php' }
            options {
                skipDefaultCheckout()
                timeout(time: 10, unit: 'MINUTES')
            }
            steps {
                sshagent(credentials: [env.DEPLOY_CRED]) {
                    // 這把 key 在主機端被 forced command 綁定，只能執行 deploy-app $APP
                    sh 'ssh -o StrictHostKeyChecking=yes -o BatchMode=yes -o LogLevel=ERROR deploy@172.17.0.1 deploy "$DEPLOY_SHA"'
                }
            }
        }
    }

    post {
        always {
            notifySlack('result')
        }
    }
}
