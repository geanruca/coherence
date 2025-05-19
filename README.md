# Laravel Coherence

**Laravel Coherence** is a developer tool that uses AI (via [Prism](https://github.com/prism-php/prism)) to validate whether the attributes of your Eloquent models are logically coherent before they are saved. It's useful for catching absurd or invalid values early, especially in data-critical applications.

---

## 🚀 Features

- 🔎 Coherence validation using LLMs (OpenAI, etc.)
- 📚 Automatic or manual model description
- 🧠 Dynamic prompt generation with model context
- 🛑 Strict mode (throws exception) or safe mode (logs result)
- 📋 Polymorphic logging of all coherence checks

---

## 📦 Installation

```bash
composer require geanruca/laravel-coherence
