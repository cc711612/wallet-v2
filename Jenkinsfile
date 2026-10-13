// wallet CI/CD（usongrat Jenkins）
// 流程：composer install → 測試 → 人工核准 → 透過受限 SSH 呼叫主機上的 deploy-app wallet
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
            // 不佔用 agent，等人工按「部署」，30 分鐘沒按就中止
            options { timeout(time: 30, unit: 'MINUTES') }
            steps {
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
            node('php') {
                withCredentials([string(credentialsId: 'slack-webhook', variable: 'SLACK_URL')]) {
                    withEnv(["RESULT=${currentBuild.currentResult}"]) {
                        sh '''
                            case "$RESULT" in
                              SUCCESS) icon=":white_check_mark:"; word="部署成功" ;;
                              ABORTED) icon=":double_vertical_bar:"; word="未部署（取消或核准逾時）" ;;
                              *)       icon=":x:"; word="失敗" ;;
                            esac
                            MSG="$icon $JOB_NAME #$BUILD_NUMBER $word — ${DEPLOY_SHA:-unknown} ${COMMIT_MSG:-} ($BUILD_URL)"
                            MSG="$MSG" php -r 'echo json_encode(["text" => getenv("MSG")], JSON_UNESCAPED_UNICODE);' \
                              | curl -sf -X POST -H 'Content-Type: application/json' --data @- "$SLACK_URL" >/dev/null || true
                        '''
                    }
                }
            }
        }
    }
}
