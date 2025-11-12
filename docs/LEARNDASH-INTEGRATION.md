# Integración con LearnDash - Cloud Sync

## 📖 Descripción General

El plugin **Secure PDF Viewer** ahora incluye integración completa con **LearnDash**, el LMS líder para WordPress. Esta integración permite la sincronización bidireccional automática de cursos y sus materiales PDF con servicios en la nube (Google Drive, Dropbox, SharePoint).

---

## ✨ Características Principales

### 🔍 Detección Automática
- **Detecta automáticamente** si LearnDash está instalado y activo
- Compatible con LearnDash 3.x y 4.x
- No interfiere con la funcionalidad normal de LearnDash

### 📚 Sincronización de Cursos
- **Cursos** (`sfwd-courses`)
- **Lecciones** (`sfwd-lessons`)
- **Temas** (`sfwd-topic`)
- **Cuestionarios** (`sfwd-quiz`)

### ☁️ Servicios en la Nube Soportados
- ✅ **Google Drive**
- ✅ **Dropbox**
- ✅ **SharePoint / OneDrive**

### 🔄 Sincronización Bidireccional
- **WordPress → Nube**: Los cambios en los cursos se sincronizan automáticamente
- **Nube → WordPress**: Los archivos añadidos en la nube se detectan y sincronizan
- Sincronización manual disponible desde la interfaz de administración
- Sincronización masiva de múltiples cursos a la vez

---

## 🚀 Instalación y Configuración

### Requisitos Previos
1. WordPress 5.0 o superior
2. PHP 7.0 o superior
3. LearnDash 3.x o 4.x instalado y activado
4. Al menos un servicio de nube configurado (Google Drive, Dropbox o SharePoint)

### Pasos de Configuración

#### 1. Configurar Servicios en la Nube
Ve a **CloudSync Dashboard** → **OAuth** y configura tus credenciales:

**Google Drive:**
- Client ID
- Client Secret
- Refresh Token (obtenido vía OAuth)

**Dropbox:**
- App Key (Client ID)
- App Secret (Client Secret)
- Refresh Token

**SharePoint:**
- Tenant ID
- Client ID
- Client Secret

#### 2. Configurar Carpetas Raíz
En **CloudSync Dashboard** → **Config**:
- Define la carpeta raíz para cada servicio
- Ejemplo: `/Cursos LearnDash` en Google Drive

#### 3. Activar Sincronización Automática
- **Modo de sincronización**: Bidirectional
- **Intervalo**: 10 minutos (recomendado)
- **Auto-sync**: Activado

---

## 💻 Uso

### Desde el Editor de Cursos

Cuando editas un curso de LearnDash, verás un nuevo metabox:

**CloudSync - Sincronización en la Nube**
- Estado actual de sincronización
- IDs de carpetas en cada servicio
- Fecha de última sincronización
- Botón "Sincronizar Ahora" para sincronización manual

### Desde la Página de Gestión

Ve a **CloudSync Dashboard** → **Cursos LearnDash**

Aquí puedes:
- Ver todos tus cursos de LearnDash
- Ver el estado de sincronización de cada curso
- Sincronizar cursos individuales
- Sincronización masiva (todos los cursos seleccionados)
- Ver cuántas lecciones tiene cada curso
- Ver la fecha de última sincronización

---

## 🗂️ Estructura de Carpetas en la Nube

La integración crea automáticamente una estructura organizada:

```
Carpeta Raíz (ej: /Cursos LearnDash)
│
├── Nombre del Curso 1/
│   ├── Lección 1/
│   │   ├── documento.pdf
│   │   └── Tema 1/
│   │       └── material.pdf
│   ├── Lección 2/
│   └── PDFs del curso/
│
├── Nombre del Curso 2/
│   ├── Lección 1/
│   └── Lección 2/
│
└── ...
```

---

## 🔄 Sincronización Automática

### Triggers de Sincronización

La sincronización se activa automáticamente cuando:

1. **Se publica un nuevo curso** en LearnDash
2. **Se actualiza un curso existente**
3. **Se añade/modifica una lección**
4. **Se añade/modifica un tema**
5. **Según el intervalo configurado** (cron job)

### Proceso de Sincronización

1. **Detección de cambios** en WordPress o en la nube
2. **Creación de carpetas** si no existen
3. **Subida de archivos PDF** nuevos o modificados
4. **Descarga de archivos** añadidos en la nube
5. **Actualización de metadatos** (IDs de carpetas, timestamps)
6. **Registro en logs** para auditoría

---

## 📝 Metadatos Almacenados

Para cada curso sincronizado, se guarda:

### `_cloudsync_folder_id`
Array con los IDs de las carpetas en cada servicio:
```php
array(
    'google' => 'folder_id_in_google_drive',
    'dropbox' => 'folder_id_in_dropbox',
    'sharepoint' => 'folder_id_in_sharepoint'
)
```

### `_cloudsync_last_sync`
Timestamp UNIX de la última sincronización exitosa.

---

## ⚙️ AJAX Endpoints

### Sincronizar Curso Individual
```javascript
POST /wp-admin/admin-ajax.php
action: 'ld_sync_course_to_cloud'
nonce: [nonce]
course_id: [ID del curso]
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "message": "Curso sincronizado correctamente"
    }
}
```

### Sincronización Masiva
```javascript
POST /wp-admin/admin-ajax.php
action: 'ld_bulk_sync_courses'
nonce: [nonce]
course_ids: [array de IDs]
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "success": 10,
        "failed": 0,
        "total": 10
    }
}
```

---

## 🎣 Hooks y Filtros

### Actions

```php
// Después de sincronizar un curso
do_action('cloudsync_after_sync_ld_course', $course_id, $folder_ids);

// Antes de eliminar carpetas en la nube
do_action('cloudsync_delete_cloud_folders', $folder_ids);

// Cuando se detecta LearnDash
do_action('cloudsync_learndash_detected', $version);
```

### Filters

```php
// Personalizar nombre de carpeta del curso
add_filter('cloudsync_ld_course_folder_name', function($name, $course_id) {
    return 'CURSO-' . $course_id . '-' . sanitize_title($name);
}, 10, 2);

// Personalizar servicios a sincronizar
add_filter('cloudsync_ld_enabled_services', function($services) {
    return array('google', 'dropbox'); // Solo Google Drive y Dropbox
});
```

---

## 🔍 Debugging y Logs

### Activar Modo Desarrollador
En **CloudSync Dashboard** → **Advanced**:
- Marcar "Developer Mode"
- Los logs se escriben en el error_log de WordPress

### Logs Útiles
```
[CloudSync] LearnDash detected - Version: 4.5.0
[CloudSync] Plugin initialized - LearnDash Integration: Active
[CloudSync] LearnDash course saved: Introducción al Marketing (ID: 123)
[CloudSync] Would sync course "Introducción al Marketing" to Google Drive
[CloudSync] Course "Introducción al Marketing" synced successfully
```

---

## ⚠️ Limitaciones y Consideraciones

### Tamaño de Archivos
- Los PDFs muy grandes (>100MB) pueden causar timeouts
- Considera aumentar `max_execution_time` en PHP si es necesario

### Cuota de API
- Cada servicio tiene límites de API (llamadas por día)
- La sincronización se reintenta automáticamente en caso de límite

### Conflictos de Nombres
- Si dos cursos tienen el mismo nombre, se añade el ID del curso
- Ejemplo: `Mi Curso` → `Mi Curso (ID-123)`

### Eliminación
- Al eliminar un curso en WordPress, las carpetas en la nube NO se eliminan automáticamente
- Esto es por seguridad (evitar pérdida accidental de datos)
- Puedes eliminar manualmente desde el servicio en la nube

---

## 🐛 Troubleshooting

### LearnDash No Detectado

**Problema**: El plugin dice que LearnDash no está activo

**Soluciones**:
1. Verifica que LearnDash está activo en Plugins
2. Verifica la versión de LearnDash (mínimo 3.0)
3. Desactiva y reactiva el plugin Secure PDF Viewer
4. Revisa los logs de error

### Cursos No Se Sincronizan

**Problema**: Los cursos no se sincronizan automáticamente

**Soluciones**:
1. Verifica que tienes al menos un servicio configurado
2. Verifica que el cron de WordPress está funcionando
3. Prueba sincronización manual desde el metabox
4. Revisa que el curso está "Publicado" (no "Borrador")
5. Verifica los logs para errores específicos

### Error de Permisos en la Nube

**Problema**: "Error al crear carpeta en [servicio]"

**Soluciones**:
1. Verifica que el refresh token es válido
2. Reautoriza el servicio en OAuth
3. Verifica permisos de la aplicación en la consola del servicio
4. Verifica que la carpeta raíz existe y tienes permisos de escritura

---

## 📊 Performance y Optimización

### Sincronización Asíncrona
- Las sincronizaciones no bloquean la interfaz de administración
- Se ejecutan mediante `wp_schedule_single_event`
- Los eventos se procesan en segundo plano

### Caché y Optimización
- Los IDs de carpetas se cachean en postmeta
- No se hacen llamadas API innecesarias
- La sincronización incremental solo procesa cambios

### Recomendaciones
- **Para <50 cursos**: Intervalo de 10 minutos
- **Para 50-200 cursos**: Intervalo de 30 minutos
- **Para >200 cursos**: Sincronización manual o programada en horarios de bajo tráfico

---

## 🔐 Seguridad

### Credenciales
- Todas las credenciales se almacenan encriptadas (AES-256-CBC)
- Los refresh tokens nunca se exponen en el frontend
- Los nonces se verifican en todas las peticiones AJAX

### Permisos
- Solo administradores pueden configurar sincronización
- Solo administradores pueden sincronizar cursos
- Los estudiantes NO tienen acceso a funcionalidades de sincronización

### Validación
- Todos los inputs se sanitizan
- Los IDs de cursos se validan antes de procesar
- Se previene CSRF en todas las acciones

---

## 📚 Ejemplos de Código

### Sincronizar un Curso Programáticamente

```php
// Obtener la integración
$plugin = SecurePDFViewer::get_instance();
$ld_integration = $plugin->get_learndash_integration();

// Sincronizar curso específico
$course_id = 123;
$result = $ld_integration->sync_course_to_cloud($course_id);

if ($result) {
    echo "Curso sincronizado correctamente";
} else {
    echo "Error al sincronizar curso";
}
```

### Obtener Todos los Cursos Sincronizados

```php
$args = array(
    'post_type' => 'sfwd-courses',
    'meta_query' => array(
        array(
            'key' => '_cloudsync_folder_id',
            'compare' => 'EXISTS'
        )
    )
);

$synced_courses = get_posts($args);

foreach ($synced_courses as $course) {
    $folder_ids = get_post_meta($course->ID, '_cloudsync_folder_id', true);
    echo sprintf("Curso: %s - Carpetas: %s\n",
        $course->post_title,
        print_r($folder_ids, true)
    );
}
```

### Personalizar Nombre de Carpeta

```php
add_filter('cloudsync_ld_course_folder_name', function($name, $course_id) {
    $course = get_post($course_id);
    $date = get_the_date('Y-m', $course_id);

    return sprintf('%s - %s', $date, $name);
}, 10, 2);

// Resultado: "2025-01 - Introducción al Marketing"
```

---

## 🎓 Casos de Uso

### Universidad/Escuela
- Sincronizar todos los cursos del semestre automáticamente
- Estructura organizada por departamento/facultad
- Backup automático de materiales educativos
- Compartir fácilmente con profesores asistentes

### Plataforma E-Learning
- Distribución de contenido a múltiples nubes
- Redundancia y disponibilidad
- Acceso offline para estudiantes (descarga desde nube)
- Análisis de uso de materiales

### Empresa/Capacitación
- Materiales de entrenamiento siempre actualizados
- Fácil distribución a equipos remotos
- Backup automático de certificaciones y materiales
- Integración con sistemas corporativos (SharePoint)

---

## 🚀 Roadmap Futuro

Funcionalidades planeadas para próximas versiones:

- [ ] Sincronización de videos (no solo PDFs)
- [ ] Compresión automática de archivos grandes
- [ ] Sincronización selectiva (elegir qué cursos sincronizar)
- [ ] Reportes de uso de almacenamiento
- [ ] Integración con otros LMS (Tutor LMS, LifterLMS)
- [ ] Versionado de archivos
- [ ] Papelera de reciclaje antes de eliminación definitiva

---

## 📞 Soporte

Para soporte o preguntas:
- Email: soporte@miceanou.com
- GitHub Issues: [PDFviewer-AUTOMATICO/issues](https://github.com/miceaia/PDFviewer-AUTOMATICO/issues)

---

## 📄 Licencia

GPL v2 or later

---

**Última actualización**: Noviembre 2025
**Versión del Plugin**: 4.4.0
**Compatibilidad LearnDash**: 3.x - 4.x
