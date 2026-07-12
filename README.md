# Probo Example: WordPress

A **WordPress** site whose home page displays the environment variables a
Probo CI build injects into a container. It is the WordPress port of
[probo-example-php](../probo-example-php), delivered as a must-use plugin
([`wordpress/wp-content/mu-plugins/probo-environment.php`](wordpress/wp-content/mu-plugins/probo-environment.php))
that serves the **site root** (also available at `/probo-env`) rendering two
tables:

- **Build variables** — the documented Probo catalog (`PROBO_ENVIRONMENT`,
  `BUILD_ID`, `BRANCH_NAME`, …) with live values where present.
- **Secrets & other injected variables** — everything else Probo made visible to
  the process (organization/project secrets and any custom variables),
  discovered dynamically.

The nav includes a **login option**: anonymous visitors get a *Log in* link to
`wp-login.php`, logged-in users get *Log out* — both round-trip back to
`/probo-env`, and the page shows the current username when logged in. Because
it ships as a **mu-plugin** and claims the site root itself, it is always
active with no database activation step and is the home page no matter what
front page the imported database dump is configured with.

Unlike the standalone Node/Python/.NET examples (which run a single app process),
this is a full WordPress codebase built by Probo's **LAMP + WordPressApp**
plugin from a database dump. The page callback reads the container environment
with `getenv()` and applies the same filter the container's `envvars-swap.sh`
uses (`compgen -e | grep -Ev '^APACHE_|^(HOME|LANG|PWD|PATH|OLDPWD|SHLVL|_)$'`),
so only the injected variables remain.

## Example Database

The example database for this example can be downloaded
[here](https://probosupportfiles.blob.core.windows.net/utils/wordpress.sql.gz) —
be sure to place the `wordpress.sql.gz` file in your assets if you are trying to
build it on your own Probo.CI account.

## Logging in

The sample database ships with an administrator account for exercising the
login option:

| Field    | Value              |
| -------- | ------------------ |
| Username | `wpadmin`          |
| Password | `P@$$W0RDFlynFlp!` |

Use the **Log in** link in the demo page's nav (or go to `/wp-login.php`
directly). After logging in, the nav switches to **Log out** and the WordPress
version card shows the current username.

## Run locally

```bash
docker compose up -d
# then open http://localhost:8013
```

Simulate a Probo build environment by uncommenting the `SetEnv` lines at the
bottom of [`wordpress/.htaccess`](wordpress/.htaccess) (variables must reach
the web server's PHP process, which `docker exec -e` cannot do):

```apache
SetEnv PROBO_ENVIRONMENT TRUE
SetEnv BUILD_ID abc123
SetEnv BRANCH_NAME main
SetEnv DEMO_CUSTOM_VAR hello-from-probo
```

The page reads the environment live per request — no restart needed — so the
tables reflect whatever the server currently exposes. On a real Probo build the
variables come from the container environment via `envvars-swap.sh` instead.

## How it runs in a Probo WordPress container

`.probo.yaml` uses `type: lamp` with `php: 8.4` and the built-in **WordPressApp**
plugin, which installs the site from a database dump:

```yaml
type: lamp
php: 8.4
database: mariadb:11.4
assets:
  - wordpress.sql.gz

steps:
  - name: Setup WordPress
    plugin: WordPressApp
    subDirectory: wordpress
    database: wordpress.sql.gz
    databasePrefix: 'ex_'
    databaseGzipped: true
    flushCaches: true
    updatePlugins: true
```

The WordPressApp plugin serves the site from the `wordpress` subdirectory,
imports the gzipped dump using the `ex_` table prefix, rewrites the site URL to
the build domain, flushes caches, and updates plugins. Standard Probo build
variables are passed automatically and populate the first table; secrets and
custom variables appear in the second.

## Project layout

```
.probo.yaml          Probo build config (lamp type + WordPressApp plugin step)
docker-compose.yml   Local Apache/PHP + MariaDB stack (port 8013)
wordpress/           WordPress codebase (docroot)
wordpress/wp-content/mu-plugins/probo-environment.php
                     Env filtering + page render + login/logout nav link
```