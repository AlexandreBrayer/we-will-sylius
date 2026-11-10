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
/** Choix à pictogrammes (yack-icons) : alignement du texte. */
export { default as TextAlignStart } from '@lucide/svelte/icons/text-align-start';
export { default as TextAlignCenter } from '@lucide/svelte/icons/text-align-center';
export { default as TextAlignEnd } from '@lucide/svelte/icons/text-align-end';
/** Choix à pictogrammes : alignement vertical des deux colonnes. */
export { default as AlignStartHorizontal } from '@lucide/svelte/icons/align-start-horizontal';
export { default as AlignCenterHorizontal } from '@lucide/svelte/icons/align-center-horizontal';
export { default as AlignEndHorizontal } from '@lucide/svelte/icons/align-end-horizontal';
/** Choix à pictogrammes : position du visuel (texte + image). */
export { default as PanelLeft } from '@lucide/svelte/icons/panel-left';
export { default as PanelRight } from '@lucide/svelte/icons/panel-right';
/** Choix à pictogrammes : largeur (contenu, large, pleine). */
export { default as AlignHorizontalSpaceAround } from '@lucide/svelte/icons/align-horizontal-space-around';
export { default as RectangleHorizontal } from '@lucide/svelte/icons/rectangle-horizontal';
export { default as MoveHorizontal } from '@lucide/svelte/icons/move-horizontal';
/** Espacement (yack-spacing) : côtés liés ou indépendants, pas coché du menu, un pictogramme par côté. */
export { default as Link2 } from '@lucide/svelte/icons/link-2';
export { default as Link2Off } from '@lucide/svelte/icons/link-2-off';
export { default as Check } from '@lucide/svelte/icons/check';
export { default as ArrowUpToLine } from '@lucide/svelte/icons/arrow-up-to-line';
export { default as ArrowRightToLine } from '@lucide/svelte/icons/arrow-right-to-line';
export { default as ArrowDownToLine } from '@lucide/svelte/icons/arrow-down-to-line';
export { default as ArrowLeftToLine } from '@lucide/svelte/icons/arrow-left-to-line';
