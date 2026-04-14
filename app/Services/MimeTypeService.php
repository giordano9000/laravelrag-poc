<?php

namespace App\Services;

class MimeTypeService
{
    /**
     * Supported mime types for document processing.
     */
    private const SUPPORTED_MIME_TYPES = [
        'application/pdf',
        'text/plain',
        // Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Spreadsheet
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        // Archive (will be extracted)
        'application/zip',
        'application/x-zip-compressed',
    ];

    /**
     * Supported file extensions.
     */
    private const SUPPORTED_EXTENSIONS = [
        'pdf', 'txt', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'doc', 'docx', 'zip',
    ];

    /**
     * Extension to mime type mapping.
     */
    private const EXTENSION_MIME_MAP = [
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'zip'  => 'application/zip',
    ];

    /**
     * Check if a mime type is supported for import/processing.
     */
    public static function isSupported(?string $mimeType): bool
    {
        if (!$mimeType) {
            return false;
        }

        // Check exact match
        if (in_array($mimeType, self::SUPPORTED_MIME_TYPES)) {
            return true;
        }

        // Check image/* prefix
        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        return false;
    }

    /**
     * Check if a mime type is a ZIP archive.
     */
    public static function isZip(?string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/zip',
            'application/x-zip-compressed',
        ]);
    }

    /**
     * Get all supported mime types.
     */
    public static function getSupportedMimeTypes(): array
    {
        return self::SUPPORTED_MIME_TYPES;
    }

    /**
     * Get all supported extensions.
     */
    public static function getSupportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Get mime type for a file extension.
     */
    public static function getMimeTypeForExtension(string $extension): ?string
    {
        return self::EXTENSION_MIME_MAP[strtolower($extension)] ?? null;
    }

    /**
     * Get all extension to mime type mappings.
     */
    public static function getExtensionMimeMap(): array
    {
        return self::EXTENSION_MIME_MAP;
    }

    /**
     * Get supported mime types as JavaScript array string.
     */
    public static function getSupportedMimeTypesForJs(): string
    {
        $mimeTypes = array_merge(
            self::SUPPORTED_MIME_TYPES,
            // Add image/* as individual examples
            ['image/jpeg', 'image/png', 'image/gif']
        );

        return json_encode($mimeTypes);
    }
}
