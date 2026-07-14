<?php

declare(strict_types=1);

function posthog_load_env(): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $token = (string) (getenv('POSTHOG_PROJECT_TOKEN') ?: '');
    $host  = (string) (getenv('POSTHOG_HOST') ?: '');

    if ($token === '' || $host === '') {
        $envFile = __DIR__ . '/.env';
        if (is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $val] = explode('=', $line, 2);
                $name = trim($name);
                $val  = trim($val, " \t\"'");
                if ($name === 'POSTHOG_PROJECT_TOKEN' && $token === '') {
                    $token = $val;
                } elseif ($name === 'POSTHOG_HOST' && $host === '') {
                    $host = $val;
                }
            }
        }
    }

    $loaded = [
        'token' => $token,
        'host'  => $host !== '' ? $host : 'https://us.i.posthog.com',
    ];
    return $loaded;
}

function posthog_render_snippet(): void
{
    $cfg = posthog_load_env();
    if ($cfg['token'] === '') {
        return;
    }

    $token = htmlspecialchars($cfg['token'], ENT_QUOTES, 'UTF-8');
    $host  = htmlspecialchars($cfg['host'], ENT_QUOTES, 'UTF-8');

    $user       = function_exists('current_user') ? current_user() : null;
    $identifyJs = '';
    if ($user !== null) {
        $userId = (int) ($user['user_id'] ?? 0);
        $role   = json_encode((string) ($user['role'] ?? ''));
        if ($userId > 0) {
            $identifyJs = "posthog.identify('" . $userId . "', { role: " . $role . " });";
        }
    }
    ?>
<script>
!(function (t, e) {
    var o, n, p, r;
    e.__SV || ((window.posthog = e), (e._i = []), (e.init = function (i, s, a) {
        function g(t, e) { var o = e.split('.'); 2 == o.length && ((t = t[o[0]]), (e = o[1])); t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; }
        ((p = t.createElement('script')).type = 'text/javascript'), (p.crossOrigin = 'anonymous'), (p.async = !0),
        (p.src = s.api_host.replace('.i.posthog.com', '-assets.i.posthog.com') + '/static/array.js'),
        (r = t.getElementsByTagName('script')[0]).parentNode.insertBefore(p, r);
        var u = e; for (void 0 !== a ? (u = e[a] = []) : (a = 'posthog'), u.people = u.people || [],
        u.toString = function (t) { var e = 'posthog'; return 'posthog' !== a && (e += '.' + a), t || (e += ' (stub)'), e; },
        u.people.toString = function () { return u.toString(1) + '.people (stub)'; },
        o = 'init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagResult isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug'.split(' '), n = 0; n < o.length; n++) g(u, o[n]);
        e._i.push([i, s, a]);
    }), (e.__SV = 1));
})(document, window.posthog || []);
posthog.init('<?= $token ?>', {
    api_host: '<?= $host ?>',
    defaults: '2026-05-30',
});
<?php if ($identifyJs !== ''): ?>
<?= $identifyJs ?>
<?php endif; ?>
</script>
<?php
}
