import { test, expect } from '@playwright/test';

test('test', async ({ page }) => {
  await page.goto('https://www.facebook.com/');
  await page.getByTestId('royal-email').click();
  await page.getByTestId('royal-email').fill('mcalexizoficial');
  await page.getByTestId('royal-email').click();
  await page.getByTestId('royal-email').click();
  await page.getByTestId('royal-pass').click();
  await page.locator('#passContainer').click();
  await page.getByTestId('royal-pass').click();
  await page.getByTestId('royal-pass').fill('AleGar27$');
  await page.getByTestId('royal-login-button').click();
  await page.getByRole('button', { name: 'Cerrar' }).click();
  await page.getByRole('button', { name: 'Tu perfil', exact: true }).click();
  await page.getByRole('button', { name: 'Cambiar a Miltoner' }).click();
  await page.getByRole('button', { name: '¿Qué estás pensando, Miltoner?' }).click();
  await page.getByRole('button', { name: 'Foto/video' }).click();
  await page.getByRole('button', { name: 'Foto/video' }).setInputFiles('msg_931_2025-10-29_14-45-13_soy_la_morra_bonita_que_vienes_a_visitar_a_una_col.png');
  await page.getByRole('button', { name: 'Siguiente' }).click();
  await page.getByRole('button', { name: 'Publicar', exact: true }).click();
});
