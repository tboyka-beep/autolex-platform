import { test, expect } from '@playwright/test';

test.describe('ALX vehicle media identity', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('catalogue media matches make/model and rejects wrong generation', async ({ page }) => {
    const response = await page.goto('/autok/', { waitUntil: 'networkidle' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    await page.evaluate(() => {
      const addCard = (id, make, label) => {
        const card = document.createElement('article');
        card.id = id;
        card.className = 'alx3-vehicle-card';
        card.innerHTML = `<header><div class="alx3-brand-mark">ALX</div><div><span>${make}</span><h2>${label}</h2></div></header>`;
        document.body.appendChild(card);
      };

      addCard('alx-media-corsa-model', 'Opel', 'Corsa');
      addCard('alx-media-corsa-f', 'Opel', 'Corsa F');
      addCard('alx-media-corsa-e', 'Opel', 'Corsa E');
      addCard('alx-media-astra', 'Opel', 'Astra');
      addCard('alx-media-wrong-make', 'BMW', 'Corsa F');

      addCard('alx-media-qashqai-model', 'Nissan', 'Qashqai');
      addCard('alx-media-qashqai-j12', 'Nissan', 'Qashqai J12');
      addCard('alx-media-qashqai-j11', 'Nissan', 'Qashqai J11');
      addCard('alx-media-juke', 'Nissan', 'Juke');
    });

    for (const id of ['alx-media-corsa-model', 'alx-media-corsa-f']) {
      const image = page.locator(`#${id} img[data-alx-verified-vehicle-media="1"]`);
      await expect(image).toHaveCount(1);
      await expect(image).toHaveAttribute('src', /Opel_Corsa_F_IMG_5815/);
    }

    for (const id of ['alx-media-qashqai-model', 'alx-media-qashqai-j12']) {
      const image = page.locator(`#${id} img[data-alx-verified-vehicle-media="1"]`);
      await expect(image).toHaveCount(1);
      await expect(image).toHaveAttribute('src', /Nissan_Qashqai_%28J12%29_IMG_4900/);
    }

    await expect(page.locator('#alx-media-corsa-e img[data-alx-verified-vehicle-media="1"]')).toHaveCount(0);
    await expect(page.locator('#alx-media-astra img[data-alx-verified-vehicle-media="1"]')).toHaveCount(0);
    await expect(page.locator('#alx-media-wrong-make img[data-alx-verified-vehicle-media="1"]')).toHaveCount(0);
    await expect(page.locator('#alx-media-qashqai-j11 img[data-alx-verified-vehicle-media="1"]')).toHaveCount(0);
    await expect(page.locator('#alx-media-juke img[data-alx-verified-vehicle-media="1"]')).toHaveCount(0);
  });

  test('named homepage media hides unrelated stock photos and restores verified vehicle media', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'networkidle' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    await page.evaluate(() => {
      const featuredCopy = document.querySelector('.alx-featured-copy');
      if (!featuredCopy) throw new Error('Featured copy missing');
      featuredCopy.innerHTML = '<div class="alx-featured-data"><h2>Opel Astra</h2></div>';
    });

    const featuredMedia = page.locator('.alx-featured-media');
    await expect(featuredMedia).toBeHidden();
    await expect(featuredMedia).toHaveAttribute('data-alx-media-fail-closed', '1');

    await page.evaluate(() => {
      const name = document.querySelector('.alx-featured-data h2');
      if (!name) throw new Error('Featured vehicle name missing');
      name.textContent = 'Nissan Qashqai';
    });

    await expect(featuredMedia).toBeVisible();
    await expect(featuredMedia).toHaveAttribute('data-alx-media-fail-closed', '0');
    await expect(featuredMedia.locator('img')).toHaveAttribute('src', /Nissan_Qashqai_%28J12%29_IMG_4900/);
  });

  test('comparison photos fail closed unless both named vehicles are verified', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'networkidle' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    await page.evaluate(() => {
      const card = document.querySelector('.alx-compare-card');
      if (!card) throw new Error('Comparison card missing');
      let data = card.querySelector('.alx-compare-data');
      if (!data) {
        data = document.createElement('div');
        data.className = 'alx-compare-data';
        card.appendChild(data);
      }
      data.innerHTML = '<div class="alx-compare-names"><strong>Nissan Qashqai</strong><span>VS.</span><strong>Opel Astra</strong></div>';
    });

    const photoRow = page.locator('.alx-compare-vehicles--photos');
    await expect(photoRow).toBeHidden();
    await expect(photoRow).toHaveAttribute('data-alx-media-fail-closed', '1');

    await page.evaluate(() => {
      const names = document.querySelectorAll('.alx-compare-data .alx-compare-names strong');
      if (names.length !== 2) throw new Error('Comparison names missing');
      names[1].textContent = 'Opel Corsa';
    });

    await expect(photoRow).toBeVisible();
    await expect(photoRow).toHaveAttribute('data-alx-media-fail-closed', '0');
    const images = photoRow.locator('img');
    await expect(images.nth(0)).toHaveAttribute('src', /Nissan_Qashqai_%28J12%29_IMG_4900/);
    await expect(images.nth(1)).toHaveAttribute('src', /Opel_Corsa_F_IMG_5815/);
  });
});
