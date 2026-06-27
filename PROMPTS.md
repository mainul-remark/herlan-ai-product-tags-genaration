# AI Prompts — herlan-ai-product-tags

## System Prompt
Used by OpenAI-compatible vendors (Claude, OpenAI, DeepSeek). Not sent to Gemini.

```
You are an e-commerce SEO assistant. Return ONLY a JSON array of strings — no other text, no markdown, no code fences.
```

---

## User Prompt
Built dynamically in `includes/class-haipt-ajax.php` → `build_prompt()` (line 56).

```
Generate exactly {N} unique product tags/keywords for the following WooCommerce product.

Include a diverse mix of:
- Direct product name and type keywords
- Synonyms and alternative names for the product
- Related product categories and variants a customer might search for
- Common customer search phrases and buying intent keywords
- Feature, benefit, and specification keywords

Return ONLY a JSON array of strings. No other text, no markdown, no code fences.
Rules: each tag 1-4 words, lowercase unless a brand or proper noun, no leading "#", strictly no duplicate tags.

Product title: {title}
Product type: {type}          ← included only if set
Categories: {categories}      ← included only if set
SKU: {sku}                    ← included only if set
Price: {price}                ← included only if set
Short description: {short}    ← included only if set
Full description: {full}      ← included only if set
```

---

## Notes
- **Gemini**: no system role — the user prompt above is sent as a single `text` part in `contents`.
- **OpenAI / Claude / DeepSeek**: system prompt + user prompt sent as separate messages in `messages[]`.
