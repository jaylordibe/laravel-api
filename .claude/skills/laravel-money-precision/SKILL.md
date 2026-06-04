---
name: laravel-money-precision
description: Use whenever code touches money, rates, or any decimal arithmetic — adding a monetary/decimal column, computing amounts, casting values, or parsing numeric input. Covers the mandatory Brick\Math\BigDecimal rule (never float), BigDecimalCast on models, BaseRequest::bigDecimal() for input, and MathUtil for division/precision.
---

# Money & decimal precision (BigDecimal, never float)

**All monetary amounts, rates, and precise decimals use `Brick\Math\BigDecimal` — never PHP `float`/`int` arithmetic.** Float rounding errors are silent and compound; they are not acceptable for money. Treat any `+ - * /` on a money value with floats as a bug. The template ships `App\Casts\BigDecimalCast` and `App\Utils\MathUtil` ready for this.

## The four touchpoints

1. **Model column → cast with `BigDecimalCast`.** In the model's `casts()`:
   ```php
   protected function casts(): array
   {
       return [
           'amount' => BigDecimalCast::class,
       ];
   }
   ```
   The attribute then reads/writes as a `BigDecimal`. Document it as `@property BigDecimal $amount`.

2. **Request input → `BaseRequest::bigDecimal()`.** Never `(float) $request->input(...)`. In `toData()`: `amount: $this->bigDecimal('amount'),`. Validate with `'amount' => ['required', 'numeric']`.

3. **Division & precision → `App\Utils\MathUtil::divide($dividend, $divisor)`.** `BigDecimal` division throws unless you give scale/rounding. `MathUtil::divide()` uses 20-decimal scale, `RoundingMode::DOWN`, and returns `BigDecimal::zero()` on a zero divisor — use it rather than `->dividedBy(...)` ad hoc so precision and divide-by-zero handling are uniform.

4. **Reference/rate tables → `resources/json/`.** Store rate tables as JSON and compare with BigDecimal, not float ranges.

## Rules of thumb

- Compare with `->compareTo()` / `->isZero()` / `->isGreaterThan()` — not `==`/`<`/`>`.
- Add/subtract with `->plus()` / `->minus()`; multiply with `->multipliedBy()`; divide only via `MathUtil`.
- Let `BigDecimal` serialize as-is through the Resource; don't cast to float to "clean it up."
- In tests, assert against the value the API serializes; don't recompute expected amounts with float math.
