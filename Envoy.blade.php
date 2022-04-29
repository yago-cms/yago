@setup
    require __DIR__.'/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
@endsetup

@servers(['blacksmith' => env('PLESK_SSH'), 'localhost' => '127.0.0.1'])

@story('init')
    build-local
    clone-remote
    deploy-local-modules
    init-remote
    deploy-local
@endstory

@story('deploy')
    build-local
    deploy-local-modules
    deploy-remote
    deploy-local
@endstory

@story('deploy-all')
    build-local
    deploy-remote
    deploy-local
    get-local-db
    set-remote-db
    set-remote-storage
@endstory

@story('sync-local-db')
    get-remote-db
    set-local-db
@endstory

@story('sync-remote-db')
    get-local-db
    set-remote-db
@endstory

@story('sync-local-storage')
    set-local-storage
@endstory

@story('sync-remote-storage')
    set-remote-storage
@endstory

@task('set-remote-db', ['on' => 'localhost'])
    ssh {{ env('PLESK_SSH') }} "mysql -u{{ env('PLESK_DB_USERNAME') }} '-p{{ env('PLESK_DB_PASSWORD') }}' -h {{ env('PLESK_DB_HOST') }}  {{ env('PLESK_DB_DATABASE') }}" < /tmp/{{ env('DB_DATABASE') }}.sql
@endtask

@task('get-remote-db', ['on' => 'localhost'])
    ssh {{ env('PLESK_SSH') }} "mysqldump -u{{ env('PLESK_DB_USERNAME') }} '-p{{ env('PLESK_DB_PASSWORD') }}' -h {{ env('PLESK_DB_HOST') }} --no-tablespaces {{ env('PLESK_DB_DATABASE') }}" > /tmp/{{ env('PLESK_DB_DATABASE') }}.sql
@endtask

@task('set-local-db', ['on' => 'localhost'])
    docker-compose exec -T mysql mysql -u{{ env('DB_USERNAME') }} -p{{ env('DB_PASSWORD') }} {{ env('DB_DATABASE') }} < /tmp/{{ env('PLESK_DB_DATABASE') }}.sql
@endtask

@task('get-local-db', ['on' => 'localhost'])
    docker-compose exec -T mysql mysqldump -u{{ env('DB_USERNAME') }} -p{{ env('DB_PASSWORD') }} --no-tablespaces {{ env('DB_DATABASE') }} > /tmp/{{ env('DB_DATABASE') }}.sql
@endtask

@task('set-local-storage', ['on' => 'localhost'])
    scp -r {{ env('PLESK_SSH') }}:./httpdocs/storage/app/public/upload/ storage/app/public/
@endtask

@task('set-remote-storage', ['on' => 'localhost'])
    scp -r storage/app/public/upload/ {{ env('PLESK_SSH') }}:./httpdocs/storage/app/public/upload/
@endtask

@task('build-local', ['on' => 'localhost'])
    echo "Building modules"

    composer show | grep -oP '^yago-cms/\K.*(?=\sdev-main)' | while read line ; do npm run prod -w @yago/$line ; done

    echo "Building frontend"

    npm run prod
@endtask

@task('deploy-local', ['on' => 'localhost'])
    echo "Deploying frontend assets"

    scp -r public/css/ public/js/ {{ env('PLESK_SSH') }}:./httpdocs/public/
@endtask

@task('deploy-local-modules', ['on' => 'localhost'])
    echo "Deploying module assets"

    composer show | grep -oP '^yago-cms/\K.*(?=\sdev-main)' | while read line ; do scp -r packages/$line/resources/dist {{ env('PLESK_SSH') }}:./httpdocs/packages/$line/resources/ ; done
@endtask

@task('clone-remote', ['on' => 'blacksmith'])
    cd ~/httpdocs

    rm -rf ..?* .[!.]* *

    git clone https://{{ env('GITHUB_USERNAME') }}:{{ env('GITHUB_PASSWORD') }}@github.com/{{ env('GITHUB_REPOSITORY') }} .
@endtask

@task('init-remote', ['on' => 'blacksmith'])
    cd ~/httpdocs

    # config
    cp .env.example .env

    sed -i 's/APP_NAME=Laravel/APP_NAME="{{ env('PLESK_SITE') }}"/g' .env
    sed -i 's/APP_ENV=local/APP_ENV=production/g' .env
    sed -i 's/APP_DEBUG=true/APP_DEBUG=false/g' .env
    sed -i 's,APP_URL=http://localhost,APP_URL={{ env('PLESK_URL') }},g' .env
    sed -i 's/DB_HOST=mysql/DB_HOST={{ env('PLESK_DB_HOST') }}/g' .env
    sed -i 's/DB_DATABASE=/DB_DATABASE={{ env('PLESK_DB_DATABASE') }}/g' .env
    sed -i 's/DB_USERNAME=/DB_USERNAME={{ env('PLESK_DB_USERNAME') }}/g' .env
    sed -i 's/DB_PASSWORD=/DB_PASSWORD={{ env('PLESK_DB_PASSWORD') }}/g' .env

    /opt/plesk/php/8.0/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader

    /opt/plesk/php/8.0/bin/php artisan down
    /opt/plesk/php/8.0/bin/php artisan migrate:fresh --seed --force
    /opt/plesk/php/8.0/bin/php artisan key:generate
    /opt/plesk/php/8.0/bin/php artisan config:clear
    /opt/plesk/php/8.0/bin/php artisan storage:link
    /opt/plesk/php/8.0/bin/php artisan up
@endtask

@task('deploy-remote', ['on' => 'blacksmith'])
    echo "Deploying"
    cd ~/httpdocs

    /opt/plesk/php/8.0/bin/php artisan down

    git remote set-url origin https://{{ env('GITHUB_USERNAME') }}:{{ env('GITHUB_PASSWORD') }}@github.com/{{ env('GITHUB_REPOSITORY') }}
    git reset --hard
    git clean -fd
    git pull origin main

    /opt/plesk/php/8.0/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader

    /opt/plesk/php/8.0/bin/php artisan migrate --force
    /opt/plesk/php/8.0/bin/php artisan lighthouse:cache
    /opt/plesk/php/8.0/bin/php artisan config:clear
    /opt/plesk/php/8.0/bin/php artisan cache:clear

    /opt/plesk/php/8.0/bin/php artisan up
@endtask