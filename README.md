# Vending Machine Challenge

Implementación en PHP de una máquina expendedora que acepta monedas, dispensa productos y devuelve cambio

## Requisitos

- PHP 8.2 o superior
- Composer

## Instalación

```bash
composer install
```

## Ejecutar la API (modo demo)

```bash
php -S localhost:8080 -t public
```

### Docker / Docker Compose

```bash
# Construir imagen y ejecutar con docker compose
docker compose up --build

# O solo con Docker
docker build -t vending-machine .
docker run --rm -p 8080:8080 vending-machine
```

Endpoints principales:

| Método | Ruta              | Descripción                                  |
| ------ | ----------------- | -------------------------------------------- |
| GET    | `/inventory`      | Inventario actual (nombre, precio, cantidad) |
| GET    | `/inserted-amount`| Saldo insertado por el usuario               |
| POST   | `/insert-coin`    | Inserta una moneda (`value`: 0.05, 0.10, 0.25, 1.00) |
| POST   | `/select-item`    | Compra un ítem (`item`: Water, Juice, Soda)  |
| POST   | `/return-coins`   | Devuelve las monedas insertadas              |
| POST   | `/restock`        | Operación de servicio (reponer ítems y/o cambio) |

Ejemplos de uso con `curl`:

```bash
# Inventario
curl http://localhost:8080/inventory

# Insertar monedas y comprar
curl -X POST http://localhost:8080/insert-coin -H "Content-Type: application/json" -d '{"value": 1.00}'
curl -X POST http://localhost:8080/select-item -H "Content-Type: application/json" -d '{"item": "Water"}'

# Devolver monedas
curl -X POST http://localhost:8080/return-coins

# Operación de servicio: reponer ítems y configurar cambio
curl -X POST http://localhost:8080/restock -H "Content-Type: application/json" \
  -d '{"item": "Water", "amount": 2, "availableChange": {"1.00": 2, "0.25": 4, "0.10": 5}}'
```

## Tests

```bash
composer install
vendor/bin/phpunit tests --testdox
```

Cobertura principal:
- Inserción y validación de monedas
- Compra de ítems con cambio
- Devolución de monedas
- Operaciones de error básicas

## Estructura relevante

- `public/index.php`: bootstrap de Slim y registro de rutas.
- `src/application/service/VendingMachineService.php`: lógica de negocio.
- `src/infrastructure/persistence/LocalStorage.php`: persistencia en JSON.
- `src/infrastructure/http/controller/VendingMachineController.php`: capa HTTP.
- `tests/application/service/VendingMachineServiceTest.php`: pruebas principales.

## Notas

- Se usa `LocalStorage` para simular almacenamiento en disco (`storage/vending_machine.json`). Se crea automáticamente si no existe.
- El endpoint `/restock` permite al personal de servicio ajustar tanto el stock de ítems como la reserva de monedas.
- Incluye `Dockerfile` y `docker-compose.yml` para ejecutar la aplicación sin instalar PHP localmente.

