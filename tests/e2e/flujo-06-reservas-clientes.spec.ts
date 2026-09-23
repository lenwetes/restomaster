import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 06: Reservas y Clientes VIP', () => {
  test('Hostess visualiza calendario de reservas y verifica estado de comensales', async ({ page }) => {
    // 1. Iniciar sesión como hostess / administrador
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@restomaster.com');
    await page.fill('input[type="password"]', 'RestoDemo2026');
    await page.click('button[type="submit"]');

    // 2. Navegar a Reservas
    await page.goto('/reservas');
    await expect(page).toHaveURL(/.*reservas/);
    await expect(page.locator('text=Reservas, text=Calendario, text=Comensales').first()).toBeVisible();
  });
});
