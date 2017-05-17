pipeline {
  agent any
  stages {
    stage('Build') {
      steps {
        sh 'composer install'
        sh './vendor/bin/phpcs --config-set installed_paths vendor/magento/marketplace-eqp'
      }
    }
    stage('Static code analysis') {
      steps {
        sh '''mkdir -p build/logs
'''
        sh '''# PHPCS
vendor/bin/phpcs --standard=MEQP2 --extensions=php,phtml --report=checkstyle --report-file=build/logs/checkstyle.xml --ignore=vendor --runtime-set ignore_errors_on_exit 1 --runtime-set ignore_warnings_on_exit 1 .'''
        sh '''# PHPCPD
vendor/bin/phpcpd -n --exclude=vendor --log-pmd=build/logs/pmd-cpd.xml . || true'''
      }
    }
    stage('Report') {
      steps {
        checkstyle(healthy: '100', unHealthy: '999', pattern: 'build/logs/checkstyle.xml')
        dry(pattern: 'build/logs/pmd-cpd.xml')
      }
    }
    stage('Package and Validate') {
      steps {
        sh 'zip -r gigya_magento2-1.0.0.zip . -x ".git/*" "vendor/*"'
        sh 'php /home/x2i/gigya/marketplace-tools/validate_m2_package.php gigya_magento2-1.0.0.zip > build/logs/validate-m2-package.log'
      }
    }
  }
}
