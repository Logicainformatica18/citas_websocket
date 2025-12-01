import { chromium } from 'playwright';

const url = process.argv[2];

(async () => {
    const browser = await chromium.launch({
        headless: true,
    });

    const page = await browser.newPage({
        userAgent:
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36",
        bypassCSP: true
    });

    try {
        await page.goto(url, {
            waitUntil: "domcontentloaded",
            timeout: 60000
        });

        // Scroll profundo (muy importante)
        await page.evaluate(async () => {
            await new Promise(resolve => {
                const distance = 700;
                let total = 0;
                const timer = setInterval(() => {
                    window.scrollBy(0, distance);
                    total += distance;

                    if (total > document.body.scrollHeight * 2) {
                        clearInterval(timer);
                        resolve();
                    }
                }, 200);
            });
        });

        await page.waitForTimeout(1500);

        const html = await page.content();
        console.log(html);

    } catch (err) {
        console.error("SCRAPER ERROR:", err.message);
    } finally {
        await browser.close();
    }
})();
