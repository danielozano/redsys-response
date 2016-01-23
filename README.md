# Redsys Response

Basado en: https://github.com/eusonlito/redsys-Fake

Esta aplicación simula una respuesta configurable de redsys.

Primero cambia el nombre del fichero config.php por config.local.php para desarrollos locales.

Seguidamente rellena el array en config.local.php con los datos de tu TPV destino:
  * sha256key => varlo de la clave SHA256 del TPV
  * DS_MERCHANT_MERCHANTCODE => FUC
  * DS_MERCHANT_TERMINAL => Número de terminal
  * DS_MERCHANT_CURRENCY => Código de moneda (978 = €)

En index.php puedes cambiar el tipo de respuesta que quieres, síncrona o asíncrona:
```php
$response->createFakeResponse('sincrona');
$response->createFakeResponse('asincrona');
```

En tu pasarela de pago simplemente apunta hacia el directorio web/

Necesitarás activar mod_rewrite en Apache.
### TODO:
  * Testear respuesta asíncrona
  * Añadir documentación
  * Seguir estandar PSR-2 