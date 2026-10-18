========================================================================
SISTEMA DE GESTIÓN DE CRÉDITOS - MÓDULO INTEGRANTE 1
========================================================================
Estudiante: Bryan Henry Alvarado Apodaca (Nro. Lista: 06)
Carrera: Informática - Universidad Mayor de San Andrés (UMSA)
Rol Asignado: Integrante 1 - Clientes y Datos Iniciales
Fecha: Octubre 2026
========================================================================

1. TABLAS ASIGNADAS (5 TABLAS)
------------------------------------------------------------------------
- cliente:
  Gestión integral de datos personales, CI, teléfono, correo, dirección y estado.
- informacion_economica:
  Registro de ocupación, empleador, ingreso mensual, otros ingresos y gastos.
- tipo_credito:
  Catálogo de productos crediticios (tasas, plazos min/max, montos min/max, parámetros JSON).
  Base fundamental para que el Integrante 2 enlace las solicitudes.
- historial_crediticio:
  Registro y consulta de burós de información (Infocred, ASFI) con scores y obligaciones.
- notificacion:
  Bandeja de avisos y alertas emitidas a los clientes con control de estado de envío.

2. ESTRUCTURA Y ARCHIVOS DEL MÓDULO
------------------------------------------------------------------------
modulos/integrante1_clientes/
├── index.php                 -> Dashboard principal con métricas en tiempo real.
├── ARQUITECTURA_INTEGRANTE1_BRYAN.md -> Documento formal de decisiones técnicas y seguridad.
├── persistencia/             -> Capa de datos desacoplada (Patrón DAO con PDO)
│   ├── Database.php          -> Conexión Singleton resiliente con fallback XAMPP / Docker.
│   ├── ClienteDAO.php        -> Transacciones atómicas para cliente e información económica.
│   ├── TipoCreditoDAO.php    -> CRUD y catálogo con validación de JSON.
│   ├── HistorialDAO.php      -> Consultas de buró y análisis de riesgo.
│   └── NotificacionDAO.php   -> Gestión de alertas y control de despacho.
├── clientes/
│   ├── index.php             -> Cartera de clientes con búsqueda y filtros por estado.
│   ├── nuevo.php             -> Formulario de registro (Datos Personales + Económicos).
│   ├── editar.php            -> Modificación de datos personales y capacidad de pago.
│   ├── ver.php               -> Ficha 360° (Datos, margen neto, buró y notificaciones).
│   └── cambiar_estado.php    -> Activación / Desactivación segura.
├── tipos_credito/
│   ├── index.php             -> Catálogo visual en tarjetas y tabla de productos.
│   ├── nuevo.php             -> Configuración de nuevo tipo de crédito.
│   └── editar.php            -> Modificación de tasas, plazos y límites.
├── historial/
│   └── index.php             -> Reporte centralizado de calificaciones de buró y scores.
├── notificaciones/
│   ├── index.php             -> Bandeja de entrada con filtros de estado.
│   └── nueva.php             -> Redacción manual de avisos con validación CSRF.
└── includes/
    ├── header.php            -> Encabezado corporativo, barra de navegación y helpers XSS.
    └── footer.php            -> Pie de página y librerías Bootstrap 5.

3. INSTRUCCIONES DE EJECUCIÓN
------------------------------------------------------------------------
OPCIÓN A: En Windows con XAMPP (Compañeros de equipo y docente)
1. Copiar la carpeta 'siscrediticio' dentro de 'C:\xampp\htdocs\'.
2. Iniciar Apache y MySQL desde el XAMPP Control Panel.
3. En phpMyAdmin (http://localhost/phpmyadmin), crear la BD 'sistema_creditos'
   e importar 'sistema_creditos.sql' y 'datos_prueba_integrante1.sql'.
4. Abrir en el navegador:
   http://localhost/siscrediticio/modulos/integrante1_clientes/

OPCIÓN B: En Linux con PHP embebido
1. Iniciar servidor:
   php -S 127.0.0.1:8000 -t siscrediticio
2. Abrir en el navegador:
   http://127.0.0.1:8000/modulos/integrante1_clientes/

OPCIÓN C: Con Docker
1. Iniciar contenedores:
   docker compose up -d
2. Base de datos phpMyAdmin disponible en: http://127.0.0.1:8080/

4. CREDENCIALES DE PRUEBA EN BD (TABLA USUARIO)
------------------------------------------------------------------------
- Administrador: usuario 'admin' / contraseña 'password'
- Oficial de Crédito (Bryan): usuario 'balvarado' / contraseña 'password'

5. DOCUMENTACIÓN ADJUNTA
------------------------------------------------------------------------
Para más detalles sobre la arquitectura compartida, medidas de seguridad
(PDO preparado, prevención XSS con htmlspecialchars, tokens CSRF) y la
compatibilidad sin alterar el index raíz, consultar:
- modulos/integrante1_clientes/ARQUITECTURA_INTEGRANTE1_BRYAN.md
- documentos/ARQUITECTURA_MODULO1_BRYAN.md
