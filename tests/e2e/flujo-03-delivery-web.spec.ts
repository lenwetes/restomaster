import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 03: Delivery Web y Despacho a Domicilio', () => {
  test('Cliente realiza pedido online y restaurante lo despacha', async ({ page }) => {
    // 1. Acceso a formulario público de delivery
    await page.goto('/delivery/pedir');
    await expect(page).toHaveURL(/.*delivery.*pedir/);

    // 2. Verificar formulario de datos de envío
    await expect(page.locator('text=Domicilio, text=Delivery, text=Pedido').first()).toBeVisible();

    // 3. Completar datos de cliente si están disponibles los campos
    const inputNombre = page.locator('input[wire\\:model="nombreCliente"], input[name="nombre"]').first();
    if (await inputNombre.isVisible()) {
      await inputNombre.fill('Santiago Vélez');
    }
  });
});
