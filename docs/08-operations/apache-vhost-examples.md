# Ejemplos de configuracion Apache

Este documento recoge configuraciones de referencia para servir Xestify con
Apache+PHP en un solo origen.

## Produccion

Usar cuando Xestify se sirve desde la raiz del host.

```apache
<VirtualHost *:80>
    ServerName xestify.local
    DocumentRoot "C:/Proyectos/Xestify"

    <Directory "C:/Proyectos/Xestify">
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    # Required modules:
    #   LoadModule rewrite_module modules/mod_rewrite.so
    #   LoadModule headers_module modules/mod_headers.so

    ErrorLog "logs/xestify-error.log"
    CustomLog "logs/xestify-access.log" combined
</VirtualHost>
```

## Desarrollo

Usar cuando se necesite exponer tambien los tests HTML del frontend.

```apache
<VirtualHost *:80>
    ServerName xestify.local
    DocumentRoot "C:/Proyectos/Xestify"

    <Directory "C:/Proyectos/Xestify">
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    # Required modules:
    #   LoadModule rewrite_module modules/mod_rewrite.so
    #   LoadModule headers_module modules/mod_headers.so

    # Dev-only browser access for frontend tests.
    SetEnvIf Request_URI "^/" ENABLE_TEST=1

    ErrorLog "logs/xestify-dev-error.log"
    CustomLog "logs/xestify-dev-access.log" combined
</VirtualHost>
```

## Alias o subruta

Usar cuando la aplicacion cuelgue de una subruta como `http://localhost/xestify/`.

```apache
Alias /xestify "C:/Proyectos/Xestify"

<Directory "C:/Proyectos/Xestify">
    AllowOverride All
    Require all granted
    Options FollowSymLinks
    SetEnvIf Request_URI "^/xestify/" ENABLE_TEST=1
</Directory>
```

Notas:

- Quitar la regla `SetEnvIf ... ENABLE_TEST=1` en produccion.
- Con esta configuracion funcionan `/xestify/`, `/xestify/api/*`,
  `/xestify/plugins/*` y, en desarrollo, `/xestify/tests/*`.
- El enrutado vive en [/.htaccess](../../.htaccess).
- Los tests HTML tambien quedan accesibles desde el propio servidor
  (`Require local`) aunque no se expongan a clientes remotos.
