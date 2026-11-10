// Pictogrammes de l'éditeur (E3) : liste fermée des icônes Lucide (@lucide/svelte, licence ISC) utilisées par le
// panneau du bloc et la palette. C'est le seul fichier qui importe @lucide/svelte (contrôlé par
// test/editor-icons.test.ts) : une icône nouvelle s'ajoute ici, avec son usage en commentaire.
// Un import par icône (`@lucide/svelte/icons/<nom>`) : seules ces icônes entrent dans le paquet.
// En SVG inline, dessinées en `currentColor` : elles suivent la couleur du texte ; taille par la classe `ui-ic`.

/** Revenir au réglage par défaut d'une ligne du panneau. */
export { default as RotateCcw } from '@lucide/svelte/icons/rotate-ccw';
/** Champ de valeur libre fermé au niveau Charte. */
export { default as Lock } from '@lucide/svelte/icons/lock';
/** Bandeau « Hors charte ». */
export { default as TriangleAlert } from '@lucide/svelte/icons/triangle-alert';
/** Note neutre sous un champ (valeur hors charte au niveau Charte, lien introuvable, élément non affiché). */
export { default as Info } from '@lucide/svelte/icons/info';
/** « Enregistrer comme section », dans l'en-tête du panneau du bloc. */
export { default as BookmarkPlus } from '@lucide/svelte/icons/bookmark-plus';
/** Retirer un lien. */
export { default as X } from '@lucide/svelte/icons/x';
/** Liste d'éléments d'un bloc : poignée, monter, descendre, supprimer, ajouter. */
export { default as GripVertical } from '@lucide/svelte/icons/grip-vertical';
export { default as ArrowUp } from '@lucide/svelte/icons/arrow-up';
export { default as ArrowDown } from '@lucide/svelte/icons/arrow-down';
export { default as Trash2 } from '@lucide/svelte/icons/trash-2';
export { default as Plus } from '@lucide/svelte/icons/plus';
/** Palette : recherche, aide sur « ? », rubriques dépliables. */
export { default as Search } from '@lucide/svelte/icons/search';
export { default as CircleHelp } from '@lucide/svelte/icons/circle-help';
export { default as ChevronDown } from '@lucide/svelte/icons/chevron-down';
