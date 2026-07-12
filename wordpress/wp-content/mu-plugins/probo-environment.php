<?php
/**
 * Plugin Name: Probo Environment
 * Description: Displays the Probo CI build environment variables exposed to PHP at /probo-env, with a WordPress login/logout option in the nav.
 * Version:     1.0.0
 * Author:      Probo.CI
 *
 * This is a port of the standalone probo-example-php index.php demo into a
 * WordPress must-use plugin so it is always active — no database activation
 * required, which matters because this site is built from a database dump.
 * The page is delivered as a self-contained HTML document (echoed directly,
 * then exit) so the custom design is preserved rather than being wrapped by
 * the active theme.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Intercept requests to /probo-env and render the demo page. Runs at
 * template_redirect so the current user is fully resolved (for the
 * login/logout nav link) and works regardless of permalink settings.
 * Priority 0 beats redirect_canonical, so the bare /probo-env path renders
 * directly instead of 301ing to the trailing-slash form first.
 */
add_action('template_redirect', 'probo_env_maybe_render', 0);

function probo_env_maybe_render() {
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

  // Strip the site's base path so subdirectory installs still match.
  $home_path = parse_url(home_url(), PHP_URL_PATH);
  if ($home_path && strpos($path, $home_path) === 0) {
    $path = substr($path, strlen($home_path));
  }

  // Serve the demo as the home page (site root) and at /probo-env. Claiming
  // the root here means it works no matter what front page the imported
  // database dump is configured with.
  $path = trim($path, '/');
  if ($path !== '' && $path !== 'probo-env') {
    return;
  }

  // The main query 404s on this path; we own it, so send a 200.
  status_header(200);
  nocache_headers();
  probo_env_render_page();
  exit;
}

/**
 * Best-effort read of the environment across the full range of Probo PHP
 * images (5.6 -> 8.x). Merges the process environment (getenv() /
 * $_ENV — what envvars-swap.sh exposes on a real Probo build) with the
 * per-request SAPI environment derived from $_SERVER, so Apache SetEnv
 * directives (the local simulation path) are picked up as well.
 */
function probo_env_raw_env() {
  $env = array();

  // Per-request SAPI vars first: derive from $_SERVER, dropping the standard
  // CGI/request keys the web server injects so only environment-shaped
  // variables (FastCGI params, SetEnv) remain.
  $server_keys = array(
    'DOCUMENT_ROOT', 'GATEWAY_INTERFACE', 'REMOTE_ADDR', 'REMOTE_PORT',
    'REMOTE_USER', 'REQUEST_METHOD', 'REQUEST_SCHEME', 'REQUEST_URI',
    'REQUEST_TIME', 'REQUEST_TIME_FLOAT', 'QUERY_STRING', 'SCRIPT_FILENAME',
    'SCRIPT_NAME', 'PHP_SELF', 'PATH_INFO', 'PATH_TRANSLATED',
    'CONTENT_LENGTH', 'CONTENT_TYPE', 'AUTH_TYPE', 'HTTPS', 'SSL_TLS_SNI',
    'argv', 'argc',
  );
  foreach ($_SERVER as $name => $value) {
    if (strpos($name, 'HTTP_') === 0) { continue; }
    if (strpos($name, 'SERVER_') === 0) { continue; }
    if (strpos($name, 'PHP_AUTH_') === 0) { continue; }
    if (strpos($name, 'REDIRECT_') === 0) { continue; }
    if (in_array($name, $server_keys, true)) { continue; }
    if (!is_string($value)) { continue; }
    $env[$name] = $value;
  }

  // Overlay $_ENV when the runtime's variables_order populated it.
  foreach ($_ENV as $name => $value) {
    if (is_string($value)) {
      $env[$name] = $value;
    }
  }

  // Overlay the process environment (PHP 7.1+: getenv() with no arguments).
  if (PHP_VERSION_ID >= 70100) {
    $process = getenv();
    if (is_array($process)) {
      foreach ($process as $name => $value) {
        if (is_string($value)) {
          $env[$name] = $value;
        }
      }
    }
  }

  return $env;
}

/**
 * The environment variables Probo actually exposes to PHP inside
 * probo-ubuntu containers. Mirrors the filter applied by
 * probo-ubuntu/<image>/files/envvars-swap.sh at container start:
 *
 *   compgen -e | grep -Ev '^APACHE_|^(HOME|LANG|PWD|PATH|OLDPWD|SHLVL|_)$'
 *
 * These are the variables a build can rely on being visible to PHP.
 */
function probo_env_visible_env() {
  $env = probo_env_raw_env();
  $exact_exclude = array('HOME', 'LANG', 'PWD', 'PATH', 'OLDPWD', 'SHLVL', '_');

  $visible = array();
  foreach ($env as $name => $value) {
    if (strpos($name, 'APACHE_') === 0) {
      continue;
    }
    if (in_array($name, $exact_exclude, true)) {
      continue;
    }
    $visible[$name] = $value;
  }
  ksort($visible);
  return $visible;
}

/**
 * Build the env data and emit the full HTML document.
 */
function probo_env_render_page() {
  // Documented Probo build variables. Any of these that are actually present
  // in the visible environment are shown (described) in the first table.
  $known_descriptions = array(
    'PROBO_ENVIRONMENT' => 'Set to <code>TRUE</code> when running inside a Probo build container.',
    'BUILD_ID'          => 'Unique identifier for the current build.',
    'BUILD_DOMAIN'      => 'Domain where this build\'s site is accessible.',
    'BRANCH_NAME'       => 'Name of the git branch being built.',
    'BRANCH_LINK'       => 'URL to the branch on the VCS provider (GitHub, Bitbucket, etc.).',
    'COMMIT_REF'        => 'Full commit hash/reference being built.',
    'COMMIT_LINK'       => 'URL to the commit on the VCS provider.',
    'PULL_REQUEST_NAME' => 'Title of the pull request that triggered this build.',
    'PULL_REQUEST_LINK' => 'URL to the pull request on the VCS provider.',
    'SRC_DIR'           => 'Path to the source code directory inside the container.',
    'ASSET_DIR'         => 'Path to the assets directory inside the container.',
  );

  $visible = probo_env_visible_env();

  // First table: documented build variables. Listed in catalog order so the
  // page still documents the full set, with live values where present.
  $build_vars = array();
  foreach ($known_descriptions as $name => $description) {
    $present = array_key_exists($name, $visible);
    $build_vars[] = array(
      'name'        => $name,
      'value'       => $present ? $visible[$name] : false,
      'present'     => $present,
      'description' => $description,
    );
    unset($visible[$name]);
  }

  // Second table: everything else Probo injected into the environment —
  // organization/project secrets and any custom variables. Discovered
  // dynamically; there is no fixed list.
  $other_vars = array();
  foreach ($visible as $name => $value) {
    $other_vars[] = array(
      'name'  => $name,
      'value' => $value,
    );
  }

  $in_probo = (getenv('PROBO_ENVIRONMENT') === 'TRUE');

  // Login state for the nav. Logged-in users get a logout link, anonymous
  // users get a login link — both round-trip back to this page.
  $here = home_url('/probo-env');
  $logged_in = is_user_logged_in();
  $auth_url  = $logged_in ? wp_logout_url($here) : wp_login_url($here);
  $auth_text = $logged_in ? 'Log out' : 'Log in';
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Probo Environment &mdash; Demo</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #0f1117;
      --surface:  #1a1d27;
      --border:   #2a2d3e;
      --accent:   #6c63ff;
      --accent2:  #3ecfcf;
      --text:     #e2e4f0;
      --muted:    #8b8fa8;
      --green:    #3ecf8e;
      --yellow:   #f5c542;
      --radius:   12px;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Nav ── */
    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 2rem;
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(10px);
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(15, 17, 23, 0.85);
    }

    .logo {
      font-size: 1.25rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      letter-spacing: -0.5px;
    }

    nav ul { list-style: none; display: flex; gap: 2rem; }
    nav a { color: var(--muted); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
    nav a:hover { color: var(--text); }

    /* ── Hero ── */
    .hero {
      text-align: center;
      padding: 5rem 2rem 3.5rem;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(108,99,255,0.25), transparent),
        radial-gradient(ellipse 60% 40% at 80% 80%, rgba(62,207,207,0.12), transparent);
      pointer-events: none;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: rgba(108,99,255,0.15);
      border: 1px solid rgba(108,99,255,0.35);
      color: #a09af5;
      border-radius: 999px;
      padding: 0.3rem 0.9rem;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
    }

    h1 {
      font-size: clamp(2rem, 5vw, 3.25rem);
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: -1.5px;
      margin-bottom: 1rem;
    }

    h1 span {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .subtitle {
      font-size: 1.05rem;
      color: var(--muted);
      max-width: 540px;
      margin: 0 auto;
      line-height: 1.7;
    }

    /* ── Status pill ── */
    .status-bar {
      display: flex;
      justify-content: center;
      padding: 1.5rem 2rem 2.5rem;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1.25rem;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .status-pill.active {
      background: rgba(62,207,142,0.15);
      border: 1px solid rgba(62,207,142,0.4);
      color: var(--green);
    }

    .status-pill.inactive {
      background: rgba(245,197,66,0.12);
      border: 1px solid rgba(245,197,66,0.35);
      color: var(--yellow);
    }

    .dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: currentColor;
    }

    /* ── Table section ── */
    .table-section {
      max-width: 960px;
      margin: 0 auto 5rem;
      padding: 0 2rem;
      width: 100%;
    }

    .section-title {
      font-size: 1.4rem;
      font-weight: 700;
      letter-spacing: -0.4px;
      margin-bottom: 1.25rem;
    }

    .env-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .env-table thead th {
      text-align: left;
      color: var(--muted);
      font-weight: 600;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 0.6rem 1rem;
      border-bottom: 1px solid var(--border);
    }

    .env-table tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }

    .env-table tbody tr:last-child { border-bottom: none; }
    .env-table tbody tr:hover { background: rgba(255,255,255,0.03); }

    .env-table td { padding: 0.9rem 1rem; vertical-align: top; }

    .var-name {
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
      font-size: 0.82rem;
      color: var(--accent2);
      white-space: nowrap;
    }

    .var-value {
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
      font-size: 0.82rem;
    }

    .val-set {
      color: var(--green);
      word-break: break-all;
    }

    .val-unset {
      color: var(--muted);
      font-style: italic;
    }

    .var-desc { color: var(--muted); line-height: 1.6; }
    .var-desc code {
      background: rgba(255,255,255,0.08);
      padding: 1px 5px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-family: "SFMono-Regular", Consolas, monospace;
    }

    /* ── Version cards ── */
    .version-cards {
      max-width: 960px;
      margin: 0 auto 2.5rem;
      padding: 0 2rem;
      width: 100%;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.25rem;
    }

    .version-inner {
      background: linear-gradient(135deg, rgba(108,99,255,0.15), rgba(62,207,207,0.1));
      border: 1px solid rgba(108,99,255,0.4);
      border-radius: var(--radius);
      padding: 1.75rem 2rem;
      display: flex;
      align-items: center;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .version-logo {
      font-size: 2.5rem;
      line-height: 1;
      flex-shrink: 0;
    }

    .version-text { flex: 1; min-width: 160px; }

    .version-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--muted);
      margin-bottom: 0.3rem;
    }

    .version-number {
      font-size: 2.25rem;
      font-weight: 800;
      letter-spacing: -1px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1.1;
    }

    .version-full {
      font-size: 0.82rem;
      color: var(--muted);
      margin-top: 0.35rem;
      font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    }

    /* ── Footer ── */
    footer {
      margin-top: auto;
      padding: 2rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.82rem;
      border-top: 1px solid var(--border);
    }
  </style>
</head>
<body>

  <nav>
    <span class="logo">Probo Demo</span>
    <ul>
      <li><a href="#env">Environment</a></li>
      <li><a href="https://docs.probo.ci" target="_blank" rel="noopener">Docs</a></li>
      <li><a href="<?php echo esc_url($auth_url); ?>"><?php echo esc_html($auth_text); ?></a></li>
    </ul>
  </nav>

  <section class="hero">
    <div class="badge">&#x1F4E6; Probo CI &mdash; WordPress <?php echo esc_html(get_bloginfo('version')); ?></div>
    <h1>Build <span>environment</span> variables</h1>
    <p class="subtitle">
      Live values injected by Probo CI into this build container.
      Variables are populated when a pull request triggers a build.
    </p>
  </section>

  <div class="status-bar">
    <div class="status-pill <?php echo $in_probo ? 'active' : 'inactive'; ?>">
      <span class="dot"></span>
      <?php echo $in_probo ? 'Running inside a Probo build container' : 'Not running in a Probo environment'; ?>
    </div>
  </div>

  <div class="version-cards">
    <div class="version-inner">
      <div class="version-logo">&#x1F4DD;</div>
      <div class="version-text">
        <div class="version-label">WordPress Version</div>
        <div class="version-number"><?php echo esc_html(get_bloginfo('version')); ?></div>
        <div class="version-full"><?php echo $logged_in ? 'Logged in as ' . esc_html(wp_get_current_user()->user_login) : 'Not logged in'; ?></div>
      </div>
    </div>
    <div class="version-inner">
      <div class="version-logo">&#x1F418;</div>
      <div class="version-text">
        <div class="version-label">PHP Version</div>
        <div class="version-number"><?php echo esc_html(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION); ?></div>
        <div class="version-full"><?php echo esc_html(PHP_VERSION); ?></div>
      </div>
    </div>
  </div>

  <section class="table-section" id="env">
    <h2 class="section-title">Build variables</h2>
    <p class="var-desc" style="margin-bottom:1.25rem;">
      Documented variables Probo injects for every build. Values shown are read
      live from the environment PHP can see &mdash; the same set exposed by the
      container's <code>envvars-swap.sh</code>.
    </p>
    <table class="env-table">
      <thead>
        <tr>
          <th style="width:22%">Variable</th>
          <th style="width:28%">Value</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($build_vars as $var): ?>
        <tr>
          <td><span class="var-name">$<?php echo esc_html($var['name']); ?></span></td>
          <td>
            <?php if ($var['present'] && $var['value'] !== ''): ?>
              <span class="var-value val-set"><?php echo esc_html($var['value']); ?></span>
            <?php else: ?>
              <span class="var-value val-unset">not set</span>
            <?php endif; ?>
          </td>
          <td class="var-desc"><?php echo $var['description']; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="table-section" id="secrets">
    <h2 class="section-title">Secrets &amp; other injected variables</h2>
    <p class="var-desc" style="margin-bottom:1.25rem;">
      Everything else Probo made visible to PHP: organization and project
      secrets plus any custom variables. Discovered dynamically from the
      container environment &mdash; there is no fixed list.
    </p>
    <?php if (empty($other_vars)): ?>
      <p class="var-value val-unset">No additional variables are exposed to PHP in this environment.</p>
    <?php else: ?>
    <table class="env-table">
      <thead>
        <tr>
          <th style="width:35%">Variable</th>
          <th>Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($other_vars as $var): ?>
        <tr>
          <td><span class="var-name">$<?php echo esc_html($var['name']); ?></span></td>
          <td>
            <?php if ($var['value'] !== ''): ?>
              <span class="var-value val-set"><?php echo esc_html($var['value']); ?></span>
            <?php else: ?>
              <span class="var-value val-unset">empty</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <footer>
    &copy; <?php echo esc_html(date('Y')); ?> Probo CI Demo &mdash; WordPress <?php echo esc_html(get_bloginfo('version')); ?> / PHP <?php echo esc_html(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION); ?> &mdash; Built for preview environments.
  </footer>

</body>
</html>
  <?php
}
