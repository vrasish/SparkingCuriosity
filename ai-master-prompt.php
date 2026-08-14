<?php

declare(strict_types=1);

/**
 * Default master prompt for Science Fables AI storybooks.
 * Override in sparking-ai.config.php with a 'master_prompt' key.
 */
return <<<'PROMPT'
Create a children's science mystery storybook for ages 8–15 about [SCIENCE TOPIC].

The story should feel friendly, natural, interesting, and educational. It should not sound like a textbook. Build the science through a small mystery, discovery, nature walk, experiment, field trip, classroom activity, family trip, museum visit, science camp, or another fresh setting.

Use new characters and a new setting for each book so the stories do not feel repetitive.

Story requirements
Create 5–8 story pages.
Give every page a short, interesting title.
Use a natural story flow from one page to the next.
Avoid repeating the same phrases, questions, or sentence patterns.
Do not repeatedly write lines such as "the mystery was getting more interesting," "they looked closely," or "another clue."
Include accurate science appropriate for ages 8–15.
Explain difficult terms through dialogue and events.
Keep each page's text short enough to fit comfortably below the image.
Finish the story before the science summary.
Put the Science Element page only at the very end.
The Science Element page should clearly summarize the main concept, important vocabulary, and one simple takeaway.

Image and page-layout prompt

Create one separate vertical storybook page for this scene.

Use a realistic, cinematic, high-quality photo style. The people, animals, environment, lighting, and objects should look natural and believable, not cartoonish or illustrated.

Page design
Vertical children's storybook page
Warm cream or light ivory paper-textured background
Large dark navy-blue serif title centered at the top
One large realistic image with rounded corners below the title
Story text placed clearly underneath the image
Dark, readable serif body text
Plenty of spacing around the text
Thin blue divider lines with a small heart centered at the bottom
Elegant, clean, polished book layout
No comic panels
No speech bubbles
No collage layout
No colored full-page background
No page numbers unless specifically requested
No logos or watermarks
Do not place extra science facts inside the image unless the scene specifically includes a classroom chart or exhibit

Character consistency

Keep the same characters consistent across every page:

[CHARACTER 1 DESCRIPTION]
[CHARACTER 2 DESCRIPTION]
[ADULT GUIDE DESCRIPTION]

Keep their faces, approximate ages, hairstyles, skin tones, clothing, jackets, uniforms, and accessories consistent throughout the story.

Scene instructions

Page title: [PAGE TITLE]

Scene: [DESCRIBE THE LOCATION, ACTION, SCIENCE OBJECT, ANIMAL, OR EVENT]

Story text:

"[INSERT EXACT PAGE TEXT]"

Display the story text exactly as written. Do not rewrite, shorten, repeat, or add sentences.

Science Element page prompt

Create the final vertical storybook page titled "Science Element."

Use the same cream paper-textured background, navy-blue serif heading, realistic image style, readable text, and heart divider used throughout the book.

Show the characters learning from a realistic science display, diagram, model, exhibit, poster, or nature-center sign about [SCIENCE TOPIC].

Under the image, include a clear summary covering:

What [SCIENCE TOPIC] means
The important parts or stages
Important vocabulary
Why it happens or how it works
One simple takeaway sentence

This must be the last page of the book.

Display the story text exactly as written. Do not rewrite, shorten, duplicate, or add lines. Keep the Science Element page as the final page only.
PROMPT;
