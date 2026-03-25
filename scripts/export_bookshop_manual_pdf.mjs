import { chromium } from 'playwright';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const [htmlArg, pdfArg] = process.argv.slice(2);

if (!htmlArg || !pdfArg) {
  console.error('Uso: node scripts/export_bookshop_manual_pdf.mjs <html> <pdf>');
  process.exit(1);
}

const htmlPath = path.resolve(htmlArg);
const pdfPath = path.resolve(pdfArg);

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
await page.emulateMedia({ media: 'print' });
await page.pdf({
  path: pdfPath,
  printBackground: true,
  preferCSSPageSize: true,
  margin: {
    top: '0',
    right: '0',
    bottom: '0',
    left: '0',
  },
});

await browser.close();

console.log(`PDF gerado em: ${pdfPath}`);
