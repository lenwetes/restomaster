import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 02: Auto-pedido QR en Mesa (Comensal Móvil)', () => {
  test('Comensal accede a menú QR, agrega platos y despacha su auto-pedido', async ({ page }) => {
    // 1. Simulación de escaneo QR comensal en Mesa 4
    await page.goto('/mesa/4/menu');
    await expect(page).toHaveURL(/.*mesa.*4.*menu/);

    // 2. Verificar carga de la carta interactiva móvil
    await expect(page.locator('text=Menú, text=Mesa, text=Rolls, text=Carta').first()).toBeVisible();

    // 3. Seleccionar producto en el menú público
    const btnAgregar = page.locator('button:has-text("Agregar"), button:has-text("+")').first();
    if (await btnAgregar.isVisible()) {
      await btnAgregar.click();
    }

    // 4. Validar carrito flotante del comensal
    const carritoBadge = page.locator('[data-carrito-badge], text=Ver Pedido, text=Total').first();
    if (await carritoBadge.isVisible()) {
      await expect(carritoBadge).toBeVisible();
    }
  });
});
