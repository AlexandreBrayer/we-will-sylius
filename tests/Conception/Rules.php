<?php

declare(strict_types=1);

namespace App\Tests\Conception;

/**
 * Règles de conception vérifiées par les tests de ce dossier (philosophie PURJUS : une règle qu'un outil peut
 * juger vit dans un outil). Fonctions pures sur du texte : chaque test prouve aussi qu'il mord sur un exemple.
 */
final class Rules
{
    /** Tournures de récit interdites dans la doc de l'état présent et les commentaires (« prévu au lot N » reste permis : c'est la cible). */
    public const NARRATIVE = '/(depuis le lot|\bau lot \d|\bpaquet [A-Z]{1,3}\d?\b|\b(?:L\d-\d{2}[ab]?|PX-\d{2}|K-0\d|NODE-01|OFFRE-0\d[ab]?|IA-0\d)\b|retiré en V\d|à la place de l\'ancien|\bétat au\b|complété au|\bdésormais\b|\bauparavant\b|jusqu\'ici|\bvague \d|première démo du)/u';

    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Imports interdits d'un fichier de src/Yack/<Contexte>/ (ADR 008) : l'Infrastructure d'un autre contexte
     * (hors Common), ou, depuis un Domain, l'Application ou l'Infrastructure d'un autre contexte.
     *
     * @return list<string>
     */
    public static function forbiddenImports(string $context, string $layer, string $source): array
    {
        preg_match_all('/^use App\\\\Yack\\\\(\w+)\\\\(Domain|Application|Infrastructure)\\\\[^;]+;/m', $source, $uses, \PREG_SET_ORDER);
        $bad = [];
        foreach ($uses as [$line, $other, $otherLayer]) {
            if ($other === $context || 'Common' === $other) {
                continue;
            }
            if ('Infrastructure' === $otherLayer || ('Domain' === $layer && 'Application' === $otherLayer)) {
                $bad[] = trim($line);
            }
        }

        return $bad;
    }

    /**
     * Lignes de récit d'un texte ; pour un fichier de code, seulement ses lignes de commentaire.
     *
     * @return list<int> numéros de ligne
     */
    public static function narrativeLines(string $text, bool $commentsOnly): array
    {
        $found = [];
        foreach (explode("\n", $text) as $i => $line) {
            if ($commentsOnly && !preg_match('#^\s*(//|/\*|\*|\#|<!--)|\s//\s|<!--#', $line)) {
                continue;
            }
            if (preg_match(self::NARRATIVE, (string) preg_replace('/prévue?s? au lot \d/iu', '', $line))) {
                $found[] = $i + 1;
            }
        }

        return $found;
    }

    /**
     * Chemins du dépôt cités entre accents graves dans un document Markdown et qui n'existent pas.
     *
     * @return list<string>
     */
    public static function deadPaths(string $markdown, string $documentDir, callable $exists): array
    {
        preg_match_all('/`([^`\s]+\/[^`\s]+)`/', $markdown, $matches);
        $dead = [];
        foreach (array_unique($matches[1]) as $raw) {
            $path = rtrim(explode('#', $raw)[0], ':,.');
            if (preg_match('#^(/|https?:|~|\$|@|\d|application/|multipart/|text/|image/|ghcr\.io/|var/|public/media/)#', $path) || preg_match('#[*<{…:=\\\\]#', $path)) {
                continue;
            }
            $candidates = [];
            foreach (['', 'front/', $documentDir.'/', $documentDir.'/Infrastructure/', 'src/Yack/', 'src/', 'front/src/', 'front/src/lib/', 'front/src/routes/(shop)/', 'front/src/routes/'] as $base) {
                foreach (['', '.php', '.ts', '.svelte'] as $ext) {
                    $candidates[] = rtrim($base.$path, '/').$ext;
                }
            }
            if ([] === array_filter($candidates, $exists)) {
                $dead[] = $path;
            }
        }

        return $dead;
    }

    /**
     * Fichiers du dépôt (chemins relatifs) sous $dir dont le nom correspond à $pattern.
     *
     * @return list<string>
     */
    public static function files(string $dir, string $pattern): array
    {
        $root = self::root();
        if (!is_dir($root.'/'.$dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = substr($file->getPathname(), \strlen($root) + 1);
            if (preg_match($pattern, $relative) && !preg_match('#(^|/)(node_modules|vendor|var|\.svelte-kit|build)/#', $relative)) {
                $out[] = $relative;
            }
        }
        sort($out);

        return $out;
    }
}
