# Sistema de layouts SPA

## Alcance

Xestify usa JavaScript ES6+ nativo, ES Modules y DOM estándar. No existe paso de
build, transpilación ni declaraciones TypeScript. Los contratos para el editor
se expresan con JSDoc (`@param`, `@returns` y `@type`).

`ShellLayout` es el armazón persistente y solo registra zonas estructurales.
`PageLayout` recibe su instancia, inyecta los componentes de página y conserva
sus propias referencias.

## Árbol y wiring

```text
ShellLayout (persistente)
└── shell
    ├── shell-menu
    │   ├── shell-menu-nav
    │   └── shell-menu-config
    │       └── shell-menu-config-user
    ├── shell-main
    │   ├── shell-header (host estructural vacío)
    │   │   └── PageHeaderComponent [inyectado por PageLayout]
    │   │       ├── page-header-breadcrumbs
    │   │       │   └── BreadcrumbComponent
    │   │       ├── page-header-content
    │   │       │   ├── page-header-copy
    │   │       │   │   ├── page-header-title
    │   │       │   │   └── page-header-description
    │   │       │   └── page-header-toolbar
    │   │       └── page-header-bottom
    │   │           └── TabsComponent / DynamicTabs
    │   ├── shell-main-notifications
    │   ├── shell-main-content
    │   │   └── PageLayout
    │   │       ├── ListLayout
    │   │       │   └── list-panel
    │   │       └── FormLayout
    │   │           └── form-panel
    │   └── shell-main-actions
    └── shell-footer
```

La shell se crea una vez en el controlador y se inyecta en la vista activa:

```js
const shell = ShellLayout.create(root).build();
const content = shell.getTarget('shell-main-content');

new UserManager(content, {
  api,
  shellLayout: shell,
});
```

## Componentes y builders

`ShellLayout` , `PageLayout` y
sus especializaciones crean UI mediante `component.create` y los componentes
registrados. Cualquier método que acepte contenido admite un nodo, una lista de
nodos o un builder:

```js
  component.create('tabs', { tabs }).setParent(target);
```

El builder recibe el target exacto y la instancia de `ShellLayout`; no debe
buscar la shell con `querySelector`, `closest` ni selectores globales.

## Contrato de ShellLayout

```js
const shell = ShellLayout.create(container).build();
```

Métodos encadenables:

- `build()`: crea el árbol completo y registra sus referencias.
- `registerTarget(name, element)`: registra una nueva zona de integración.
- `getTarget(name)`: devuelve la referencia DOM registrada.
- `setZone(name, content)`: reemplaza una zona con nodos o un builder.
- `appendZone(name, content)`: añade contenido sin reemplazar lo existente.
- `clearZone(name)`: vacía una zona.
- `setTemplate(template)`: marca la plantilla en `shell-main-content`.
- `setMainActions(content)`: actualiza las acciones principales.
- `setContent(content)`: actualiza el contenido de página.
- `setFooter(text)`.

`registerTarget`, `setZone` y `appendZone` se reservan para zonas estructurales.
`ShellLayout` no registra targets internos de los componentes de página.

## Contrato de PageLayout

`PageLayout` recibe `shell` en sus opciones, inyecta o adopta el
`PageHeaderComponent` dentro de `shell-header` y actualiza inmediatamente sus
targets privados:

```js
const page = PageLayout.create(content, { shell })
  .setTemplate('detail')
  .setTitle('Detalle de usuario')
  .setDescription('Edita identidad, rol y acceso.')
  .setBreadcrumbs([
    { label: 'Usuarios', href: '#/users' },
    { label: 'Detalle', active: true },
  ])
  .setHeaderToolbar(buildToolbarButton())
  .setHeaderBottom((target) => buildTabs(target))
  .addMainAction(buildSaveButton())
  .setNotification(buildFeedback())
  .setFooter('Ficha de usuario')
  .setContent((target) => buildUserDetail(target))
  .build();
```

Métodos encadenables:

- `setTemplate`, `setTitle`, `setDescription` y `setBreadcrumbs`.
- `setActions`, `setHeaderToolbar`, `setHeaderBottom` y `addMainAction`.
- `setContent`, `setNotification`, `setFooter` y `build`.
- `getShell`, `getContentTarget` y `getTarget`.

El contenedor y la shell se fijan en construcción. No existen setters tardíos,
aliases ni una vía genérica para saltarse la fachada y mutar zonas arbitrarias.

`getTarget` solo expone targets propiedad de esa instancia de `PageLayout`.
Nunca deben buscarse `page-header-title`, `page-header-toolbar` u otros targets
de página mediante `shell.getTarget(...)`.

El uso standalone, sin shell, se reserva para superficies que no pertenecen al
app shell, como login. En páginas autenticadas se inyecta siempre la instancia
persistente.

```js
const loginLayout = PageLayout.create(root)
  .setTemplate('login')
  .setFooter('Xestify')
  .build();

new Login(loginLayout.getContentTarget(), { api, appDebug, sessionExpired, onSuccess });
```

La plantilla `login` crea `login-shell`, `login-content` y el footer opcional,
pero no monta navbar ni zonas propias de la shell autenticada.

## ListLayout

`ListLayout` especializa el contenido de página con `list-section`, un host y la
tabla dinámica. Añade `createTableHost` y `createTable`.

Ejemplo funcional de `UserManager`:

```js
const layout = ListLayout.create(container, { shell: shellLayout })
  .setTitle('Gestión de usuarios')
  .setDescription('Administra usuarios, roles y accesos.')
  .setHeaderToolbar(createUserButton)
  .build();

layout.createTable(users, schema, { extraColumns: userActions });
```

La vista prepara datos y columnas; el layout decide dónde vive la tabla y cómo
se integra con la shell.

## FormLayout

`FormLayout` especializa el contenido con un único `form-panel`, hijo directo de
`shell-main-content`. El panel concentra los estilos visuales y el comportamiento
común de los formularios. Sus acciones se montan directamente en
`shell-main-actions`. Añade `getPanel` y `addAction`.

Los comandos del formulario, como Guardar, Eliminar o Reset, pertenecen a
`shell-main-actions`. Los botones de navegación `Volver` o `Volver al listado`
pertenecen siempre a `page-header-toolbar` mediante `setHeaderToolbar` y usan la
variant `secondary`.

Ejemplo funcional de `UserConfig`:

```js
const layout = FormLayout.create(container, {
  shell: shellLayout,
})
  .setTitle('Configuración de usuario')
  .setDescription('Edita identidad, rol y acceso.')
  .build();

buildUserForm(layout.getPanel(), user);
layout
  .addAction(buildCancelButton())
  .addAction(buildSaveButton());
```

## JSDoc

Las APIs públicas deben declarar sus entradas y retornos junto al código:

```js
/**
 * @param {Node|Node[]|Function|null} content
 * @returns {PageLayout}
 */
setNotification(content) {
  // ...
}
```

No se crean archivos `.ts` ni `.d.ts`.

## Reglas de extensión

1. Inyectar la instancia activa de `ShellLayout`; no crear una shell por vista.
2. No introducir stores, contextos o buses para sincronizar layouts.
3. Usar exclusivamente el setter semántico de `PageLayout` correspondiente a
  cada zona; ampliar la fachada de forma explícita si aparece una zona nueva.
4. No registrar en `ShellLayout` targets internos de `PageHeader`, breadcrumbs,
  toolbar o tabs.
5. Usar builders para desacoplar al productor de la ubicación del target.
6. Publicar tabs en `page-header-bottom`, retornos de navegación en
  `page-header-toolbar` con variant `secondary` y comandos principales en
  `shell-main-actions`.
7. Mantener tablas de página en `ListLayout` y formularios de edición o
   configuración en `FormLayout`.
8. Añadir tests que comprueben el padre real de cada zona y la sincronización
   inmediata de cada integración nueva.
9. No añadir aliases, getters de targets especializados ni helpers visuales a
  los layouts. Botones, alertas y contenido pertenecen a sus componentes o a
  la página que los construye.
