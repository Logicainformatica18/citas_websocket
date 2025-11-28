import { chromium } from 'playwright';

const url = process.argv[2];
if (!url) {
    console.error("❌ No URL provided");
    process.exit(1);
}

(async () => {
    try {
        const browser = await chromium.launch({
            headless: true
        });

        const page = await browser.newPage({
            bypassCSP: true
        });

        await page.goto(url, { waitUntil: "domcontentloaded", timeout: 45000 });

        await page.waitForTimeout(1500);

        const html = await page.content();

        console.log(html);

        await browser.close();

    } catch (err) {
        console.error("❌ Playwright error:", err.message);
        process.exit(1);
    }
})();
