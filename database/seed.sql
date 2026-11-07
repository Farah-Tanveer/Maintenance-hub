/* Starter data. Run this once on a fresh database. */

INSERT INTO services (name, description, icon, base_price) VALUES
('Sweeping and cleaning', 'Regular sweeping, mopping and dusting for homes and offices.', '🧹', 1500),
('Deep cleaning', 'Top to bottom cleaning including kitchens, bathrooms and windows.', '✨', 4000),
('Water tank cleaning', 'Safe, thorough tank cleaning and sanitising.', '💧', 2500),
('Plumbing', 'Leaks, fittings, blockages and repairs.', '🔧', 2000),
('Electrical', 'Wiring, switches, lighting and fault fixing.', '💡', 2000),
('AC servicing', 'Cleaning, gas refill and repair for split and window units.', '❄️', 3500);

INSERT INTO packages (service_id, property_type, size, price)
SELECT s.id, p.property_type, p.size, ROUND(s.base_price * p.multiplier, 0)
FROM services s
CROSS JOIN (
    SELECT 'home' AS property_type, 'small' AS size, 1.0 AS multiplier
    UNION ALL SELECT 'home',   'medium', 1.6
    UNION ALL SELECT 'home',   'large',  2.4
    UNION ALL SELECT 'office', 'small',  1.4
    UNION ALL SELECT 'office', 'medium', 2.2
    UNION ALL SELECT 'office', 'large',  3.5
) p;
