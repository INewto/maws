<?php

namespace Maws\System\Helpers\Image;

use GdImage;
use Maws\System\Contract\{Changes, Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Image\Exception\ImageFailure;
use Maws\System\Helpers\Image\Exception\ImageMisuse;

#[Subject(Image::class, 'fromString', [self::PICTURE])]
#[Purpose('Картинка как значение: байты на входе, байты на выходе, файлов не трогает — читать и писать их умеет File. Две операции закрывают почти всё, что нужно сайту: fit вписывает в рамку, не растягивая маленькое, cover заполняет рамку с обрезкой по центру, как аватарка. Прозрачность сохраняется, потому что иначе у PNG она стала бы чёрной.')]
final class Image
{
    private const PICTURE = "\x47\x49\x46\x38\x37\x61\x04\x00\x02\x00\x80\x00\x00\xCC\x1E\x1C\x00\x00\x00"
        . "\x2C\x00\x00\x00\x00\x04\x00\x02\x00\x00\x02\x03\x84\x6F\x05\x00\x3B";

    private const FORMATS = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpeg',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp'  => 'bmp',
    ];

    private const WRITERS = ['png', 'jpeg', 'gif', 'webp', 'avif', 'bmp'];

    /** Форматы без прозрачности: прозрачные места заливаются белым, иначе станут чёрными. */
    private const FLAT = ['jpeg', 'bmp'];

    private function __construct(private GdImage $image, private string $format)
    {
    }

    #[Purpose('Читает картинку из байтов. Не картинка, пустые байты или формат, которого GD не знает, — false: это ответ о данных, а не сбой, и вызывающий обязан его проверить.')]
    #[Param('bytes', 'string', 'содержимое файла или ответа целиком')]
    #[Returns('static|false', 'картинка или false, если это не изображение')]
    #[Effect(Effect::NONE)]
    #[Gives(["\xB1\xB2\xB3"], false)]
    #[Gives([''], false)]
    #[Gives(['это текст, а не картинка'], false)]
    public static function fromString(string $bytes): static|false
    {
        if ($bytes === '') {
            return false;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            return false;
        }

        $format = self::FORMATS[$info['mime']] ?? null;

        if ($format === null) {
            return false;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return false;
        }

        return new static($image, $format);
    }

    #[Purpose('Отдаёт ширину в точках.')]
    #[Returns('int', 'ширина')]
    #[Effect(Effect::NONE)]
    #[Gives([], 4)]
    public function width(): int
    {
        return imagesx($this->image);
    }

    #[Purpose('Отдаёт высоту в точках.')]
    #[Returns('int', 'высота')]
    #[Effect(Effect::NONE)]
    #[Gives([], 2)]
    public function height(): int
    {
        return imagesy($this->image);
    }

    #[Purpose('Отдаёт вид, в котором картинка пришла: по нему toBytes без аргумента выбирает запись.')]
    #[Returns('string', 'png, jpeg, gif, webp, avif или bmp')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'gif')]
    public function format(): string
    {
        return $this->format;
    }

    #[Purpose('Вписывает картинку в рамку, сохраняя пропорции. Маленькую не растягивает: увеличенная миниатюра всегда мутнее исходника, поэтому рамка — предел, а не цель.')]
    #[Param('width', 'int', 'ширина рамки; меньше единицы — ошибка разработчика')]
    #[Param('height', 'int', 'высота рамки; меньше единицы — ошибка разработчика')]
    #[Returns('static', 'эта же картинка — цепочка продолжается')]
    #[Throws(ImageMisuse::class, 'сторона рамки меньше единицы')]
    #[Effect(Effect::NONE)]
    #[Changes([2, 2], [['width', [], 2], ['height', [], 1]])]
    #[Changes([10, 10], [['width', [], 4], ['height', [], 2]])]
    #[Changes([2, 1], [['width', [], 2], ['height', [], 1]])]
    #[Fails([0, 2], ImageMisuse::class)]
    #[Fails([2, 0], ImageMisuse::class)]
    #[Fails([-1, 2], ImageMisuse::class)]
    public function fit(int $width, int $height): static
    {
        self::demandSide($width, $height);

        $scale = min($width / $this->width(), $height / $this->height(), 1.0);
        $newW  = max(1, (int) round($this->width() * $scale));
        $newH  = max(1, (int) round($this->height() * $scale));

        if ($newW === $this->width() && $newH === $this->height()) {
            return $this;
        }

        $canvas = self::canvas($newW, $newH);
        imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $newW, $newH, $this->width(), $this->height());

        return $this->replace($canvas);
    }

    #[Purpose('Заполняет рамку целиком, обрезая лишнее по центру. Маленькую растягивает: cover обещает ровно такой размер, а не «не больше».')]
    #[Param('width', 'int', 'ширина рамки; меньше единицы — ошибка разработчика')]
    #[Param('height', 'int', 'высота рамки; меньше единицы — ошибка разработчика')]
    #[Returns('static', 'эта же картинка — цепочка продолжается')]
    #[Throws(ImageMisuse::class, 'сторона рамки меньше единицы')]
    #[Effect(Effect::NONE)]
    #[Changes([2, 2], [['width', [], 2], ['height', [], 2]])]
    #[Changes([1, 1], [['width', [], 1], ['height', [], 1]])]
    #[Changes([8, 8], [['width', [], 8], ['height', [], 8]])]
    #[Fails([0, 2], ImageMisuse::class)]
    #[Fails([-1, -1], ImageMisuse::class)]
    public function cover(int $width, int $height): static
    {
        self::demandSide($width, $height);

        if ($width === $this->width() && $height === $this->height()) {
            return $this;
        }

        $scale = max($width / $this->width(), $height / $this->height());
        $takeW = min($this->width(), max(1, (int) round($width / $scale)));
        $takeH = min($this->height(), max(1, (int) round($height / $scale)));
        $fromX = (int) round(($this->width() - $takeW) / 2);
        $fromY = (int) round(($this->height() - $takeH) / 2);

        $canvas = self::canvas($width, $height);
        imagecopyresampled($canvas, $this->image, 0, 0, $fromX, $fromY, $width, $height, $takeW, $takeH);

        return $this->replace($canvas);
    }

    #[Purpose('Записывает картинку байтами. Без вида берётся тот, в котором она пришла. У jpeg и bmp прозрачности нет, поэтому прозрачные места заливаются белым — иначе они стали бы чёрными.')]
    #[Param('format', 'string|null', 'png, jpeg, gif, webp, avif или bmp; null — как пришла')]
    #[Param('quality', 'int', 'качество от 0 до 100 для jpeg, webp и avif; png и gif пишутся без потерь')]
    #[Returns('string', 'содержимое файла')]
    #[Throws(ImageMisuse::class, 'вид не из списка или качество вне 0…100')]
    #[Throws(ImageFailure::class, 'эта сборка GD не умеет такой вид или запись не удалась')]
    #[Effect(Effect::NONE)]
    public function toBytes(?string $format = null, int $quality = 90): string
    {
        $format = $format ?? $this->format;

        if (!in_array($format, self::WRITERS, true)) {
            throw new ImageMisuse("вид \"{$format}\" не пишется: годятся " . implode(', ', self::WRITERS));
        }

        if ($quality < 0 || $quality > 100) {
            throw new ImageMisuse("качество от 0 до 100, получено {$quality}");
        }

        if (!function_exists('image' . $format)) {
            throw new ImageFailure("эта сборка GD не умеет писать {$format}");
        }

        $image = in_array($format, self::FLAT, true) ? $this->flatten() : $this->image;

        ob_start();

        $ok = match ($format) {
            'png'   => imagepng($image, null, 6),
            'jpeg'  => imagejpeg($image, null, $quality),
            'gif'   => imagegif($image),
            'webp'  => imagewebp($image, null, $quality),
            'avif'  => imageavif($image, null, $quality),
            'bmp'   => imagebmp($image),
        };

        $bytes = (string) ob_get_clean();

        if ($image !== $this->image) {
            imagedestroy($image);
        }

        if (!$ok || $bytes === '') {
            throw new ImageFailure("не удалось записать изображение в {$format}");
        }

        return $bytes;
    }

    private function replace(GdImage $canvas): static
    {
        imagedestroy($this->image);
        $this->image = $canvas;

        return $this;
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    private function flatten(): GdImage
    {
        $flat = imagecreatetruecolor($this->width(), $this->height());
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $this->image, 0, 0, 0, 0, $this->width(), $this->height());

        return $flat;
    }

    private static function demandSide(int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            throw new ImageMisuse("стороны рамки от единицы, получено {$width}×{$height}");
        }
    }
}
