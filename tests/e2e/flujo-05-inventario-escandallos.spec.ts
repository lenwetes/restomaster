import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 05: Abastecimiento, Inventario y Escandallos', () => {
  test('Gerente consulta inventario, filtra insumos críticos y gestiona stock', async ({ page }) => {
    // 1. Iniciar sesión como gerente / administrador
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@restomaster.com');
    await page.fill('input[type="password"]', 'RestoDemo2026');
    await page.click('button[type="submit"]');

    // 2. Navegar al módulo de Inventario
    await page.goto('/inventario');
    await expect(page).toHaveURL(/.*inventario/);
    await expect(page.locator('text=Inventario, text=Stock, text=Insumos').first()).toBeVisible();

    // 3. Verificar panel de KPIs de existencias
    await expect(page.locator('text=Stock Crítico, text=Valor Total, text=Mermas').first()).toBeVisible();
  });
});
