<?php

namespace Grokhotov\DataMigration\Helpers;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileMigrationHelper
{
    public function __construct(
        #[Autowire('%var.dir%')]
        private string $varDir,
    ) {
    }

    public function makeUploadedFile(string $oldRelativePath): ?UploadedFile
    {
        $uploadsDir = Path::join($this->varDir, 'uploads');

        if (!file_exists($uploadsDir)) {
            throw new \RuntimeException('Uploads directory not found');
        }

        if (empty($oldRelativePath)) {
            return null;
        }

        $oldPath = Path::join($this->varDir, $oldRelativePath);

        if (!is_file($oldPath)) {
            return null;
        }

        $file = new File($oldPath);

        return new UploadedFile(
            $file->getPathname(),
            $file->getFilename(),
            mime_content_type($file->getPathname()),
            null,
            true,
        );
    }
}
