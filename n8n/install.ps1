# Installs the bond workflows into n8n (run from cmd):
#   powershell -ExecutionPolicy Bypass -c "iwr -UseBasicParsing https://raw.githubusercontent.com/mrebrahim/bond/claude/dreamy-babbage-t6r4kb/n8n/install.ps1 -OutFile $env:TEMP\bond-n8n.ps1; & $env:TEMP\bond-n8n.ps1"
# Asks for the keys and the DB password; stores nothing on disk.
# Re-run with existing credentials: ... bond-n8n.ps1 -PgId <id> -EvId <id>
param([string]$PgId, [string]$EvId)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$N8N    = 'https://comp.digitalsolutionegy.com'
$RAW    = 'https://raw.githubusercontent.com/mrebrahim/bond/claude/dreamy-babbage-t6r4kb/n8n'
$FILES  = @('01_daily.json', '02_hourly.json', '03_outbox.json', '04_feedback.json', '05_inbound_whatsapp.json')
$MANUAL = @('04_feedback.json')   # needs the Google Sheets OAuth credential picked in the n8n UI, then activate by hand
$PGNAME = 'Supabase bond (Postgres)'
$EVNAME = 'Evolution API key (header: apikey)'

function Read-Plain($prompt) {
  $s = Read-Host $prompt -AsSecureString
  [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))
}

$n8nKey = Read-Plain 'n8n API key'
if (-not $EvId) { $evoKey = Read-Plain 'Evolution AUTHENTICATION_API_KEY' }
if (-not $PgId) {
  $pgHost = Read-Host 'Supabase Session pooler host (Enter = aws-0-eu-west-2.pooler.supabase.com)'
  if (-not $pgHost) { $pgHost = 'aws-0-eu-west-2.pooler.supabase.com' }
  $pgPass = Read-Plain 'Supabase database password'
}

function Call($method, $path, $body) {
  $p = @{ Method = $method; Uri = "$N8N/api/v1$path"; Headers = @{ 'X-N8N-API-KEY' = $n8nKey }; UseBasicParsing = $true }
  if ($null -eq $body -and $method -ne 'GET') { $body = '{}' }   # n8n rejects bodiless POSTs (415)
  if ($null -ne $body) {
    $p.Body = [Text.Encoding]::UTF8.GetBytes($body)
    $p.ContentType = 'application/json; charset=utf-8'
  }
  try {
    $r = Invoke-WebRequest @p
    $text = [Text.Encoding]::UTF8.GetString($r.RawContentStream.ToArray())
    if ($text) { return $text | ConvertFrom-Json }
  } catch {
    $msg = $_.Exception.Message
    if ($_.ErrorDetails.Message) { $msg += ' :: ' + $_.ErrorDetails.Message }
    throw "$method $path -> $msg"
  }
}

function Get-Raw($name) {
  $r = Invoke-WebRequest -UseBasicParsing "$RAW/$name"
  [Text.Encoding]::UTF8.GetString($r.RawContentStream.ToArray())
}

Write-Host '1) credentials' -ForegroundColor Cyan
if ($PgId) { $pg = @{ id = $PgId } } else { $pg = Call POST '/credentials' (@{
  name = $PGNAME; type = 'postgres'
  data = @{ host = $pgHost; port = 5432; database = 'postgres'; user = 'postgres.izpvbgzwiwyaoctdroxr'
            password = $pgPass; allowUnauthorizedCerts = $true; sshTunnel = $false }
} | ConvertTo-Json -Depth 4) }
Write-Host "   Postgres: $($pg.id)"
if ($EvId) { $ev = @{ id = $EvId } } else { $ev = Call POST '/credentials' (@{
  name = $EVNAME; type = 'httpHeaderAuth'; data = @{ name = 'apikey'; value = $evoKey }
} | ConvertTo-Json -Depth 4) }
Write-Host "   Evolution: $($ev.id)"

Write-Host '2) workflows' -ForegroundColor Cyan
$existing = (Call GET '/workflows?limit=250').data
foreach ($f in $FILES) {
  $json = (Get-Raw $f).Replace('"id": "SUPABASE_PG"', "`"id`": `"$($pg.id)`"").Replace('"id": "EVO_KEY"', "`"id`": `"$($ev.id)`"")
  $name = ($json | ConvertFrom-Json).name
  $old = $existing | Where-Object { $_.name -eq $name } | Select-Object -First 1
  if ($old -and $MANUAL -contains $f) { Write-Host "   skip $($old.id)  (already there - keeps its Google credential)"; continue }
  if ($old) {
    if ($old.active) { Call POST "/workflows/$($old.id)/deactivate" $null | Out-Null }
    $wf = Call PUT "/workflows/$($old.id)" $json
  } else {
    $wf = Call POST '/workflows' $json
  }
  if ($MANUAL -contains $f) { Write-Host "   OK  $($wf.id)  (open it in n8n, pick the Google Sheets credential, activate)"; continue }
  Call POST "/workflows/$($wf.id)/activate" $null | Out-Null
  Write-Host "   OK  $($wf.id)  active"
}

Write-Host ''
Write-Host 'DONE - send a screenshot of this window.' -ForegroundColor Green
