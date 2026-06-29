# AI Prompts — herlan-ai-product-tags

## System Prompt
Used by OpenAI-compatible vendors (OpenRouter, Groq, etc.). Not sent to Gemini (Gemini uses the user prompt only).

```
You are an e-commerce SEO assistant. Product info may contain typos, slang, or informal language — interpret and normalize to proper product terms. Generate diverse tags including synonyms, related terms, and names of similar/competing brand products in the same category. Return ONLY a JSON array of strings — no other text, no markdown, no code fences.
```

---

## User Prompt
Built dynamically in `includes/class-haipt-ajax.php` → `build_prompt()`.

```
Generate exactly {N} unique product tags/keywords for the following WooCommerce product.

Note: Product information may contain typos, shorthand, informal or dialect words — interpret and normalize these to standard product terms.

Include a diverse mix of:
- Direct product name and type keywords
- Synonyms, alternative names, and semantically related terms
- Names of similar or competing brand products in the same category (e.g. if the product is "Lily Shampoo", also include tags like "sunsilk shampoo", "pantene shampoo", "dove shampoo" so cross-brand searches return this product)
- Related products, accessories, and complementary items customers also search for
- Related product categories and variants
- Common customer search phrases and buying intent keywords
- Feature, benefit, and specification keywords

Return ONLY a JSON array of strings. No other text, no markdown, no code fences.
Rules: each tag 1-4 words, lowercase unless a brand or proper noun, no leading "#", strictly no duplicate tags.

Product title: {title}
Product type: {type}          ← included only if set
Categories: {categories}      ← included only if set
Attributes: {attributes}      ← included only if set (e.g. "Color: Red | Material: Cotton")
SKU: {sku}                    ← included only if set
Price: {price}                ← included only if set
Short description: {short}    ← included only if set
Full description: {full}      ← included only if set
```

---

## Fields Collected from Product Form

| Field             | Source (JS)                                              |
|-------------------|----------------------------------------------------------|
| Title             | `#title`                                                 |
| Product type      | `#product-type`                                          |
| Categories        | `#product_catchecklist input:checked`                    |
| Attributes        | `.woocommerce_attribute` — name inputs + value selects/textareas |
| SKU               | `#_sku`                                                  |
| Regular price     | `#_regular_price`                                        |
| Short description | TinyMCE `excerpt` editor                                 |
| Full description  | TinyMCE `content` editor                                 |

## Notes
- **Gemini**: no system role — the user prompt above is sent as a single `text` part in `contents`.
- **OpenAI-compat (OpenRouter, Groq)**: system prompt + user prompt sent as separate messages in `messages[]`.
- **Attributes format**: custom attributes use `|`-separated textarea values; taxonomy attributes read selected `<option>` text. Output format: `"Color: Red, Blue | Material: Cotton"`.
