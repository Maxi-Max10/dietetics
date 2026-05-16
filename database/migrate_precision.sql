-- MIGRACIÓN: Aumentar precisión decimal de cantidad de 2 a 3 decimales
-- Esto permite almacenar correctamente cantidades como 0.245kg
-- Fecha: Cuando se implemente el fix de precisión de cantidad

-- Para tablas existentes, ejecutar estos ALTER TABLE:

ALTER TABLE invoice_items MODIFY quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000;
-- Nota: Los valores existentes se redondearán. Ej: 0.25 → 0.250 (sin pérdida si ya fueron redondeados)

ALTER TABLE customer_order_items MODIFY quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000;

ALTER TABLE stock_items MODIFY quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000;

-- Verificar después de la migración:
-- SELECT id, quantity FROM invoice_items LIMIT 5;
-- SELECT id, quantity FROM customer_order_items LIMIT 5;
-- SELECT id, quantity FROM stock_items LIMIT 5;
