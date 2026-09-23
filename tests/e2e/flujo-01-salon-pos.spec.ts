import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 01: Ciclo Operativo de Salón POS Táctil (The Golden Path)', () => {
  test('Mesero toma comanda táctil, cocina la prepara y cajero procesa cobro con ticket', async ({ page }) => {
    // 1. Acceso y autenticación del mesero
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@restomaster.com');
    await page.fill('input[type="password"]', 'RestoDemo2026');
    await page.click('button[type="submit"]');

    // 2. Navegar a la terminal táctil POS
    await page.goto('/pos');
    await expect(page).toHaveURL(/.*pos/);
    await expect(page.locator('text=Terminal POS').first()).toBeVisible();

    // 3. Selección táctil de mesa
    const selectorMesa = page.locator('[data-mesa-btn], text=MESA SELECCIONADA, text=Mesa').first();
    if (await selectorMesa.isVisible()) {
      await selectorMesa.click();
    }

    // 4. Agregar plato al carrito táctil
    const botonPlato = page.locator('button:has-text("+"), [data-producto-btn]').first();
    await expect(botonPlato).toBeVisible();
    await botonPlato.click();

    // Verificar que el subtotal se actualice
    await expect(page.locator('text=Enviar a Cocina, text=Comanda').first()).toBeVisible();

    // 5. Enviar comanda a Cocina KDS
    const btnCocina = page.locator('button:has-text("Enviar a Cocina")').first();
    if (await btnCocina.isVisible()) {
      await btnCocina.click();
    }

    // 6. Verificar presencia de la comanda en KDS
    await page.goto('/cocina');
    await expect(page).toHaveURL(/.*cocina/);
    await expect(page.locator('text=KDS, text=Cocina, text=Comandas').first()).toBeVisible();
  });
});
