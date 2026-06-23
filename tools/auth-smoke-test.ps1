param(
    [string]$BaseUrl = 'http://127.0.0.1:8081',
    [string]$Username = 'cliente1',
    [string]$Password = 'pass123',
    [string]$SharedKey = ''
)

$ErrorActionPreference = 'Stop'

function Invoke-JsonPost {
    param(
        [string]$Url,
        [hashtable]$Body
    )
    return Invoke-RestMethod -Method Post -Uri $Url -ContentType 'application/json' -Body ($Body | ConvertTo-Json -Depth 5)
}

"[1/4] Login remoto"
$loginBody = @{ username = $Username; password = $Password }
if ($SharedKey -ne '') { $loginBody.shared_key = $SharedKey }
$login = Invoke-JsonPost -Url "$BaseUrl/admin/api/auth.php?action=user_login" -Body $loginBody
if (-not $login.token) { throw 'No token returned from user_login' }
"Token recibido"

"[2/4] Validate"
$validateBody = @{ token = $login.token }
if ($SharedKey -ne '') { $validateBody.shared_key = $SharedKey }
$valid = Invoke-JsonPost -Url "$BaseUrl/admin/api/auth.php?action=validate" -Body $validateBody
if (-not $valid.valid) { throw 'Validate failed' }
"Validate OK"

"[3/4] Refresh"
$refreshBody = @{ token = $login.token }
if ($SharedKey -ne '') { $refreshBody.shared_key = $SharedKey }
$refresh = Invoke-JsonPost -Url "$BaseUrl/admin/api/auth.php?action=refresh" -Body $refreshBody
if (-not $refresh.token) { throw 'Refresh failed: no token' }
"Refresh OK"

"[4/4] Revoke + validar rechazo"
$revokeBody = @{ token = $refresh.token }
if ($SharedKey -ne '') { $revokeBody.shared_key = $SharedKey }
$revoke = Invoke-JsonPost -Url "$BaseUrl/admin/api/auth.php?action=revoke" -Body $revokeBody
if (-not $revoke.ok) { throw 'Revoke failed' }
"Revoke OK"

try {
    $afterRevokeBody = @{ token = $refresh.token }
    if ($SharedKey -ne '') { $afterRevokeBody.shared_key = $SharedKey }
    $null = Invoke-JsonPost -Url "$BaseUrl/admin/api/auth.php?action=validate" -Body $afterRevokeBody
    throw 'Expected validate to fail after revoke, but it succeeded'
} catch {
    "Validate post-revoke fallo como se esperaba"
}

"Smoke test completado"
