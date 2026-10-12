<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit\Tools;

trait TempDirectoryTrait
{
    private string $tmp;

    private function makeTempDirectory(): void
    {
        $this->tmp = sys_get_temp_dir() . '/storm-tools-' . bin2hex(random_bytes(6));
        mkdir($this->tmp);
    }

    /**
     * @param array<string, string> $files relative path => contents
     */
    private function writeFiles(array $files): void
    {
        foreach ($files as $relative => $contents) {
            $path = $this->tmp . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $contents);
        }
    }

    private function removeTempDirectory(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmp);
    }

    /**
     * @return array<string, string>
     */
    private static function sourceTree(): array
    {
        return [
            'Model/User.php' => <<<'PHP'
                <?php
                namespace App\Model;

                #[\Attribute]
                final readonly class User
                {
                    public function make(): object
                    {
                        $x = Order::class;
                        return new class {};
                    }
                }
                PHP,
            'Model/Base.php' => "<?php\nnamespace App\\Model;\nabstract class Base {}\nreadonly abstract class AlsoAbstract {}\n",
            'Model/Contracts.php' => "<?php\nnamespace App\\Model;\ninterface Shape {}\ntrait Timestamps {}\n",
            'Model/Status.php' => "<?php\nnamespace App\\Model;\nenum Status: string { case Active = 'a'; }\n",
            'Model/Multi.php' => "<?php\nnamespace App\\One { class First {} }\nnamespace App\\Two { class Second {} }\nnamespace { class GlobalThing {} }\n",
            'Model/Semicolons.php' => "<?php\nnamespace App\\Three;\nclass Third {}\nnamespace App\\Four;\nclass Fourth {}\n",
            'Model/NotPhp.txt' => "<?php class Ignored {}\n",
            'Model/vendor/Dependency.php' => "<?php\nnamespace Vendor;\nclass Dependency {}\n",
            'Model/Broken.php' => "<?php\nclass {\n",
        ];
    }
}
