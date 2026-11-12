@echo off
REM ---------------------------------------------------------------------------
REM Deploy the custom code to the production site over SSH.
REM Only the theme, the core plugin and the must-use plugins are deployed;
REM WordPress core, uploads, plugins from wordpress.org and secrets are not.
REM ---------------------------------------------------------------------------
setlocal
set REMOTE_ROOT=/home/u2061-l81qnj7bg6sj/www/catericecream.com/public_html
set SSH_KEY=%USERPROFILE%\.ssh\sg_cate_ice_cream
set SSH_TARGET=u2061-l81qnj7bg6sj@gcam1091.siteground.biz
set SSH_PORT=18765
set ROOT=%~dp0..

ssh -i "%SSH_KEY%" -p %SSH_PORT% -o IdentitiesOnly=yes -o ConnectTimeout=25 -T %SSH_TARGET% "mkdir -p %REMOTE_ROOT%" || goto :fail

tar czf - -C "%ROOT%" wp-content/themes/cater-ice-cream wp-content/plugins/cater-ice-cream-core wp-content/mu-plugins | ssh -i "%SSH_KEY%" -p %SSH_PORT% -o IdentitiesOnly=yes -o ConnectTimeout=25 -T %SSH_TARGET% "tar xzf - -C %REMOTE_ROOT% && echo DEPLOY_OK"
if errorlevel 1 goto :fail

echo.
echo Deployed. Run the post-deploy checks:
echo   wp cic sheets install
echo   wp cic sheets status
exit /b 0

:fail
echo DEPLOY FAILED
exit /b 1