# Changelog

Todos los cambios relevantes de `everesthome/sendify` se documentan aquí.

## 1.0.0 - sin publicar

- Primera versión: cliente PHP del servicio Sendify e integración con Laravel
  (service provider, configuración publicable y fachada `Sendify`).
- `Sendify::Status()` devuelve un diagnóstico que no lanza excepciones y
  distingue instancia inexistente, cuenta suspendida, API key expirada, IP no
  autorizada, sin vincular, hibernando o servidor caído.
