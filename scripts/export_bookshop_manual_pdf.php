<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Views\Twig;

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

if (is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$containerBuilder = new ContainerBuilder();

$settings = require $projectRoot . '/app/settings.php';
$settings($containerBuilder);

$dependencies = require $projectRoot . '/app/dependencies.php';
$dependencies($containerBuilder);

$repositories = require $projectRoot . '/app/repositories.php';
$repositories($containerBuilder);

$container = $containerBuilder->build();

/** @var Twig $twig */
$twig = $container->get(Twig::class);

$exportDirectory = $projectRoot . '/var/exports';
if (!is_dir($exportDirectory) && !mkdir($exportDirectory, 0775, true) && !is_dir($exportDirectory)) {
    throw new RuntimeException('Não foi possível criar o diretório de exportação em ' . $exportDirectory);
}

$timestamp = new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza'));
$htmlPath = $exportDirectory . '/manual-da-livraria.html';
$pdfPath = $exportDirectory . '/manual-da-livraria.pdf';

$html = $twig->getEnvironment()->render('pages/admin-bookshop-manual-pdf.twig', [
    'manual_pdf_generated_at' => $timestamp->format('d/m/Y H:i'),
]);

file_put_contents($htmlPath, $html);

$nodeScript = $projectRoot . '/scripts/export_bookshop_manual_pdf.mjs';
$command = sprintf(
    'node %s %s %s',
    escapeshellarg($nodeScript),
    escapeshellarg($htmlPath),
    escapeshellarg($pdfPath)
);

passthru($command, $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Falha ao gerar o PDF do manual da livraria.');
}

if (!is_file($pdfPath)) {
    throw new RuntimeException('O PDF não foi criado em ' . $pdfPath);
}

printf("HTML: %s\nPDF: %s\n", $htmlPath, $pdfPath);
