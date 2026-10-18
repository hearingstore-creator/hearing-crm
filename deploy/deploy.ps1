# Push local code to the live server (crm.zenhear.com).
# Usage (from the project folder):  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1
# Never uploads: .env, vendor, node_modules, .git, storage (logs/uploads/sessions) or public/file (user uploads).
$ErrorActionPreference = 'Stop'
$Server = 'ubuntu@43.156.34.150'
$Key    = Join-Path $env:USERPROFILE '.ssh\crm_server'
$Root   = Split-Path $PSScriptRoot -Parent
$Bundle = Join-Path $env:TEMP 'crm-release.tgz'

Write-Host '1/3 Packaging code...' -ForegroundColor Cyan
tar -czf $Bundle `
    --exclude=./.env --exclude=./vendor --exclude=./node_modules --exclude=./.git `
    --exclude=./storage --exclude=./public/file --exclude=./public/storage `
    --exclude=./public/wp-demo.html --exclude=./bootstrap/cache/*.php `
    --exclude=./*.zip --exclude=./*.bat --exclude=./scratch `
    -C $Root .
if ($LASTEXITCODE -ne 0) { throw 'Packaging failed.' }
Write-Host ("    {0:N1} MB" -f ((Get-Item $Bundle).Length / 1MB))

Write-Host '2/3 Uploading...' -ForegroundColor Cyan
scp -i $Key -q $Bundle (Join-Path $PSScriptRoot 'server-update.sh') "${Server}:/tmp/"
if ($LASTEXITCODE -ne 0) { throw 'Upload failed.' }
Remove-Item $Bundle

Write-Host '3/3 Updating server (backup first)...' -ForegroundColor Cyan
$remote = "sed -i 's/\r$//; 1s/^\xEF\xBB\xBF//' /tmp/server-update.sh; bash /tmp/server-update.sh"
ssh -i $Key $Server $remote
if ($LASTEXITCODE -ne 0) { throw 'Server update failed - site was put back online; check the output above.' }

# Windows tar matches --exclude=./vendor against ANY folder named "vendor", so the
# admin vendor views get dropped from the bundle. Upload them separately.
Write-Host 'Syncing admin vendor views...' -ForegroundColor Cyan
scp -i $Key -q -r (Join-Path $Root 'resources\views\admin\vendor') "${Server}:/tmp/vendor_views"
ssh -i $Key $Server "sudo rm -rf /var/www/crm/resources/views/admin/vendor && sudo mv /tmp/vendor_views /var/www/crm/resources/views/admin/vendor && sudo chown -R www-data:www-data /var/www/crm/resources/views/admin/vendor && sudo chmod 755 /var/www/crm/resources/views/admin/vendor && sudo chmod 644 /var/www/crm/resources/views/admin/vendor/* && cd /var/www/crm && sudo -u www-data php artisan view:cache"
if ($LASTEXITCODE -ne 0) { throw 'Vendor views sync failed.' }

Write-Host 'Done: https://crm.zenhear.com' -ForegroundColor Green
