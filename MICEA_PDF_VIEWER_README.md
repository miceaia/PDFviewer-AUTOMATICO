# Visor PDF "Micea" - Documentación Completa

## 📋 Descripción

Visor PDF avanzado con sistema de subrayado inteligente basado en selección de texto, marca de agua dinámica, autosave y persistencia por usuario. Implementado con PDF.js + jQuery para WordPress.

## ✨ Características Implementadas

### 🎨 Subrayado de Texto Real
- **Selección de texto nativa** con textLayer de PDF.js
- **4 colores disponibles**: Amarillo, Verde, Azul, Rosa
- **Highlights con quads** que se ajustan al zoom
- **Borrador inteligente**: click en highlights para eliminar

### ↩️ Undo/Redo Completo
- **Stack de acciones** con historial completo
- **Atajos de teclado**:
  - `Ctrl/⌘ + Z`: Deshacer
  - `Ctrl/⌘ + Y` o `Ctrl/⌘ + Shift + Z`: Rehacer
- Revierte/aplica subrayados y borrados

### 🔍 Zoom Avanzado
- **Rango**: 50% a 300% (límites 0.5 - 3.0)
- **Incrementos**: 10% por clic
- **Recalcula highlights** automáticamente al cambiar zoom
- **Indicador en tiempo real** (#zoom-label)

### 📄 Navegación de Páginas
- **Botones**: Anterior/Siguiente con estados disabled
- **Atajos de teclado**:
  - `←`: Página anterior
  - `→`: Página siguiente
- **Contador**: Página X / Total
- **Highlights persistentes** por página

### 🖥️ Pantalla Completa
- **Activación**: Botón o API requestFullscreen
- **Escape**: ESC para salir
- **Mantiene controles** funcionales
- **Icono dinámico** (expand/collapse)

### 💾 Persistencia y Autosave
- **Autosave automático**: 1.5 segundos después de cambios
- **Doble storage**: localStorage + servidor (Ajax)
- **Indicador de estado**:
  - "Guardando..." (naranja)
  - "Guardado ✓ HH:MM" (verde)
  - "Error al guardar" (rojo)
- **Carga automática** al iniciar visor

### 💧 Marca de Agua Dinámica
- **Por página**: Se dibuja en cada render
- **Información**: Usuario + Fecha
- **Rotación**: -30° diagonal
- **Opacidad**: 0.15 (sutil pero visible)
- **Posiciones múltiples**: Centro + esquinas

### ⌨️ Atajos de Teclado
| Atajo | Acción |
|-------|--------|
| `Ctrl/⌘ + Z` | Deshacer |
| `Ctrl/⌘ + Y` | Rehacer |
| `Ctrl/⌘ + Shift + Z` | Rehacer (alternativo) |
| `←` | Página anterior |
| `→` | Página siguiente |
| `ESC` | Salir de pantalla completa |

**Bloqueados para seguridad**:
- `Ctrl/⌘ + S`: Guardar (previene download)
- `Ctrl/⌘ + P`: Imprimir (previene print)
- `Ctrl/⌘ + C`: Copiar (previene copy)

### ♿ Accesibilidad
- **Todos los botones** tienen `aria-label`
- **type="button"** en todos los botones (previene submit)
- **tabIndex=0** implícito para navegación por teclado
- **role="status"** con `aria-live="polite"` en indicadores dinámicos
- **Focus management** en pantalla completa

## 🏗️ Arquitectura

### Stack Tecnológico
```
Frontend: jQuery + PDF.js 3.4.120
Backend: WordPress PHP
Persistencia: localStorage + Ajax (WordPress)
Rendering: Canvas (PDF) + SVG (highlights) + DOM (textLayer)
```

### Capas del Visor
```
Z-Index Layer Stack:
1000: Toolbar (controles)
  10: Protection overlay (no usado actualmente)
   5: Watermark
   3: Annotation canvas (no usado - reemplazado por SVG)
   2: Text layer (selección)
   1: Highlights layer (SVG con rects)
   0: PDF canvas (base)
```

### Estructura de Datos

#### Highlight Object
```javascript
{
  id: string,              // 'hl_' + timestamp + random
  page: number,            // Número de página
  color: string,           // Hex color (#ffff00, #00ff00, etc.)
  quads: Quad[],           // Array de rectángulos
  createdAt: number,       // Timestamp
  createdBy: string        // userId
}
```

#### Quad Object
```javascript
{
  x: number,       // Posición X relativa al canvas
  y: number,       // Posición Y relativa al canvas
  w: number,       // Ancho
  h: number,       // Alto
  page: number,    // Página donde está
  scale: number    // Escala cuando se creó
}
```

#### Action Object (Undo/Redo)
```javascript
{
  type: 'ADD_HIGHLIGHT' | 'REMOVE_HIGHLIGHT',
  payload: Highlight | { id: string, highlight: Highlight }
}
```

#### AnnotationDoc (Persistencia)
```javascript
{
  userId: string,
  pdfId: string,
  highlights: Highlight[],
  lastSavedAt: number
}
```

## 🎯 Mapeo de IDs → Funciones

### Botones con IDs Requeridos
```javascript
// Navegación
#btn-prev          → prevPage()
#btn-next          → nextPage()
#page-counter      → Indicador de página (read-only)

// Colores de subrayado
#hl-yellow         → selectHighlightColor('#ffff00', 'yellow')
#hl-green          → selectHighlightColor('#00ff00', 'green')
#hl-blue           → selectHighlightColor('#00bfff', 'blue')
#hl-pink           → selectHighlightColor('#ff69b4', 'pink')

// Borrador
#hl-erase          → toggleEraserMode()

// Undo/Redo
#btn-undo          → undo()
#btn-redo          → redo()

// Zoom
#btn-zoom-out      → zoomOut()
#btn-zoom-in       → zoomIn()
#zoom-label        → Indicador de zoom (read-only)

// Pantalla completa
#btn-fullscreen    → toggleFullscreen()

// Guardar
#btn-save          → saveAnnotations(true)
#save-status       → Indicador de guardado (read-only)
```

## 🚀 Uso del Visor

### 1. Subrayar Texto
1. Haz clic en un color (amarillo, verde, azul o rosa)
2. El botón se marca como `active` (borde blanco)
3. Selecciona texto en el PDF
4. Al soltar el mouse, se crea el highlight automáticamente
5. Se activa autosave después de 1.5s

### 2. Borrar Subrayados
1. Haz clic en el botón "Borrar" (goma de borrar)
2. El visor entra en modo borrador (`spv-eraser-mode`)
3. Haz clic en cualquier highlight para eliminarlo
4. Se puede deshacer con Ctrl+Z

### 3. Deshacer/Rehacer
- **Deshacer**: Ctrl/⌘+Z o botón Deshacer
- **Rehacer**: Ctrl/⌘+Y o botón Rehacer
- Los botones se deshabilitan cuando no hay acciones disponibles

### 4. Navegación
- **Botones**: Anterior/Siguiente (se deshabilitan en límites)
- **Teclado**: Flechas izquierda/derecha
- Los highlights se mantienen al cambiar de página

### 5. Zoom
- **Botones**: + / - en la toolbar
- **Rango**: 50% a 300%
- Los highlights se recalculan automáticamente

### 6. Guardar
- **Automático**: 1.5s después de cambios
- **Manual**: Botón "Guardar"
- **Indicador**: Muestra estado y hora del último guardado

## 🔧 Configuración de WordPress

### Shortcode
```php
[secure_pdf_viewer url="URL_DEL_PDF" title="Mi PDF" pdf_id="unique_id"]
```

### Datos de Usuario
El visor obtiene automáticamente:
- `user_info['name']`: Nombre para marca de agua
- `user_info['email']`: Email del usuario
- `user_info['id']`: ID para persistencia

## 🐛 Solución de Problemas

### Botones no responden
**Causa**: Overlay bloqueando clics

**Solución implementada**:
- `.spv-controls`: `z-index: 1000; position: relative;`
- `.spv-annotation-canvas`: `pointer-events: none;`
- `.spv-text-layer`: `pointer-events: auto;` (solo para selección)
- `.spv-highlight-rect`: `pointer-events: auto;` (solo para borrar)

### Highlights no aparecen
**Verifica**:
1. Console logs: "PDF cargado: X páginas"
2. textLayer se renderiza (inspecciona DOM)
3. SVG layer tiene width/height correctos
4. Color seleccionado (botón con clase `active`)

### Autosave no funciona
**Verifica**:
1. spvAjax está definido (WordPress lo enqueue)
2. Nonce válido
3. Acción AJAX registrada en PHP: `spv_save_annotations`
4. localStorage habilitado en navegador

### Undo/Redo no funciona
**Verifica**:
1. undoStack/redoStack se populan (console.log)
2. Botones tienen evento click correctamente wireado
3. No hay errores en consola al ejecutar acción

## 📦 Archivos Modificados

```
/includes/class-pdf-viewer.php    → HTML con IDs correctos + accesibilidad
/assets/css/pdf-viewer.css        → Estilos + z-index fixes
/assets/js/pdf-viewer.js          → Lógica completa del visor
```

## 🎓 Extensiones Futuras

### Funcionalidades Sugeridas
1. **Notas de texto**: Click en highlight para agregar nota
2. **Compartir highlights**: Exportar/importar JSON
3. **Búsqueda de texto**: Input + navegación por resultados
4. **Miniaturas**: Sidebar con previews de páginas
5. **Modo oscuro**: Toggle para theme oscuro
6. **Dibujo libre**: Modo adicional para dibujar a mano alzada
7. **Formas**: Círculos, flechas, cuadros
8. **Comentarios colaborativos**: Múltiples usuarios
9. **Historial de cambios**: Timeline de ediciones
10. **Export PDF con anotaciones**: Generar PDF final

### Storage Provider Interface
```javascript
interface StorageProvider {
  load(userId: string, pdfId: string): Promise<AnnotationDoc | null>;
  save(doc: AnnotationDoc): Promise<void>;
}

// Implementaciones:
// - LocalStorageProvider ✅ (actual)
// - AjaxProvider ✅ (actual)
// - SupabaseProvider (TODO)
// - FirebaseProvider (TODO)
// - IndexedDBProvider (TODO - para PDFs grandes)
```

## ✅ Checklist de Aceptación (QA)

- [x] Todos los botones responden al clic y teclado
- [x] IDs correctos implementados (#hl-yellow, #btn-undo, etc.)
- [x] Subrayado con selección de texto real (textLayer)
- [x] Undo/Redo revierte/aplica correctamente
- [x] Zoom actualiza #zoom-label y recalcula highlights
- [x] Pantalla completa funciona con ESC
- [x] Guardar persiste y autosave funciona con debounce 1.5s
- [x] #save-status muestra estado correcto
- [x] Navegación con botones y flechas
- [x] Marca de agua visible en todas las páginas
- [x] Z-index correctos (toolbar no bloqueada)
- [x] Atajos de teclado funcionan (Ctrl+Z, Ctrl+Y, flechas)
- [x] Accesibilidad básica (aria-labels, roles)
- [x] type="button" en todos los botones
- [x] Sin errores en consola al cargar

## 🔐 Seguridad

### Prevención de Copia/Impresión
- Context menu deshabilitado en canvas
- Drag & drop bloqueado
- Ctrl+C, Ctrl+P, Ctrl+S bloqueados
- user-select: none en canvas
- NO se puede inspeccionar PDF URL desde DevTools (ofuscado)

### Protección de Datos
- Anotaciones asociadas a `userId` + `pdfId`
- Nonce validation en todas las peticiones Ajax
- Sanitización de datos en backend PHP

## 📝 Notas Técnicas

### PDF.js TextLayer
El textLayer permite selección de texto nativa del navegador. Los elementos `<span>` se posicionan absolutamente con `transform` para coincidir con el texto del PDF.

### Highlights con SVG
Se usa SVG en lugar de canvas para highlights porque:
- Mejor precisión con elementos vectoriales
- Event listeners individuales por highlight (para borrar)
- Escala perfecta al cambiar zoom
- Menor uso de memoria

### Quads vs Bounding Box
Los highlights usan array de quads (rectángulos) porque el texto seleccionado puede:
- Abarcar múltiples líneas
- Tener saltos de columna
- Incluir espacios irregulares

### Persistencia Híbrida
Se guarda en localStorage Y servidor porque:
- **localStorage**: Respuesta instantánea, funciona offline
- **Servidor**: Sincronización entre dispositivos, backup

## 🤝 Contribución

Para reportar bugs o sugerir mejoras, contacta al equipo de desarrollo.

## 📄 Licencia

Este código es parte del plugin WordPress "Secure PDF Viewer".

---

**Versión**: 2.0.0 (Micea Edition)
**Última actualización**: 2025-11-12
**Desarrollado por**: Claude (Anthropic)
