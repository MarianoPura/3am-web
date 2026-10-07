<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\RentalCatalog;

/** Primary image plus at most five managed additional images. */
final class RentalGallery
{
    public const MAX_ADDITIONAL = 5;

    public static function paths(mixed $json): array
    {
        $paths = is_array($json) ? $json : json_decode(is_string($json) ? $json : '[]',true);
        if (!is_array($paths)) { return []; }
        return array_slice(array_values(array_unique(array_filter($paths,static fn($p)=>is_string($p) && preg_match('#^micro/rentals/products/[a-f0-9]{32}\.(?:jpg|png|webp)$#D',$p)===1))),0,self::MAX_ADDITIONAL);
    }

    public static function urls(array $item): array
    {
        $paths=array_values(array_unique([(string)($item['image_path']??''),...self::paths($item['additional_image_paths']??null)]));
        $urls=[];
        foreach($paths as $path) {
            // Assigned-but-missing files retain a URL so failure is visible, never silently hidden.
            if(preg_match('#^micro/rentals/products/[a-f0-9]{32}\.(?:jpg|png|webp)$#D',$path)) { $urls[]=url('rentals/product-image/'.basename($path)); }
            elseif(($valid=RentalCatalog::imagePath($path))!==null) { $urls[]=RentalCatalog::imageUrl($valid); }
        }
        return $urls;
    }

    public static function uploads(?array $files): array
    {
        if (!$files) { return []; }
        $errors=$files['error']??[];
        if (!is_array($errors)) { throw new \InvalidArgumentException('Additional images: reselect the image files.'); }
        $count=count(array_filter($errors,static fn($e)=>(int)$e!==UPLOAD_ERR_NO_FILE));
        if ($count>self::MAX_ADDITIONAL) { throw new \InvalidArgumentException('Add at most five additional images.'); }
        $saved=[];
        try {
            foreach($errors as $key=>$error) {
                $upload=[];
                foreach(['name','type','tmp_name','error','size'] as $field) { $upload[$field]=$files[$field][$key]??null; }
                $path=RentalManagedImage::store($upload,'product');
                if ($path!==null) { $saved[]=$path; }
            }
        } catch(\Throwable $e) { foreach($saved as $path){RentalManagedImage::remove($path,'product');} throw $e; }
        return $saved;
    }

    public static function removeUnreferenced(Database $db,?string $path): void
    {
        if (!$path || $db->selectValue('SELECT id FROM rental_items WHERE image_path=? LIMIT 1',[$path])!==null
            || $db->selectValue('SELECT id FROM rental_categories WHERE image_path=? LIMIT 1',[$path])!==null) { return; }
        if ((new RentalSchema($db))->hasColumn('rental_items','additional_image_paths')
            && $db->selectValue("SELECT id FROM rental_items WHERE JSON_CONTAINS(COALESCE(additional_image_paths,'[]'),JSON_QUOTE(?)) LIMIT 1",[$path])!==null) { return; }
        RentalManagedImage::remove($path,'product');
    }
}
