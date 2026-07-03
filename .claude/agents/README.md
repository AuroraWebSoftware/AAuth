# AAuth Pre-PR Review Agents

Bu klasör, **her PR açılmadan önce** çalışan uzman review sub-agent'larını içerir. Her biri çalışma diff'ini kendi uzmanlık merceğinden inceler, **ortak formatta** ana ajana rapor verir; ana ajan hepsini tek bir **go/no-go** kararına birleştirir.

## Ajanlar

| Ajan | Mercek | Tetikleyici |
|------|--------|-------------|
| [`laravel-architect`](laravel-architect.md) | Mimari, SOLID (yalın), Laravel idiomları, API yüzeyi | Kod yapısı/tasarım değişince |
| [`security-pentest`](security-pentest.md) | OWASP Top 10, authz bypass, **veri sızıntısı**, injection, `composer audit` | Her güvenlik-duyarlı değişiklikte |
| [`test-quality`](test-quality.md) | Pint, PHPStan (yeni suppression yakalar), Pest, edge-case | Her davranış değişikliğinde |
| [`data-integrity`](data-integrity.md) | Migration, FK/ON DELETE, materialized-path bütünlüğü, **doğruluk/portability** | Şema/migration değişince |
| [`db-engine-specialist`](db-engine-specialist.md) | Postgres+MySQL **en iyi kullanım** (tree/search/JSON, ltree/GIN/FULLTEXT/CTE) — taşınabilirliği bozmadan | Sorgu/index/motor-özel değişince |

## Çalıştırma

PR açmadan önce: `/pre-pr-review` (dördünü paralel çalıştırıp tek karar üretir), veya ana ajana "review the diff before PR" de.

## 🚨 Global kırmızı çizgiler (HERHANGİ bir ajan → BLOCK)

Bu paket birçok yazılımın yetkilendirme temeli olduğundan **veri sızıntısı tek kabul edilemez sonuçtur** — gate'ler ne kadar yeşil olursa olsun, sızıntı ilk ve tek başına karar verir. Aşağıdakiler PR'ı bloke eder:

1. **Bir rolün yetkisi olmayan satırı okuyabilmesi/yazabilmesi** (herhangi bir çapraz-org / çapraz-tenant sızıntı)
2. **Boş scope kümesinin tüm tabloyu döndürmesi** (fail-open) — boş yetki → sıfır satır olmalı, asla tüm tablo
3. **ABAC'in tek başına izolasyon için kullanılması** — `AAuthABACModel` trait'i `AAuthOrganizationNode` olmadan + kural yokken tüm tabloyu ifşa eder
4. **Materialized-path `LIKE`'ın `/` ayracı olmadan** (`path.'%'`) — `'1'` → `'10'`, `'1/3'` → `'1/30'` eşler
5. **`parent_id`'nin alt-ağaç path'i yeniden hesaplanmadan değişmesi** veya `path`'in istemci girdisinden yazılması (path drift → çapraz-ağaç ifşa)
6. **Ayrıcalık alanlarının mass-assignment'ı** (`Role.type/status/organization_scope_id`, `OrganizationNode.path/parent_id`, `is_super_admin`)
7. **SQL/kolon injection** (parametrelenmemiş kolon/identifier: ABAC attribute/value, path, `whereRaw`)
8. **Yazma-tarafı authz boşluğu** — `createWith`/`updateWith`/`deleteWithAAuthOrganizationNode`'un hedef düğümü aktif rolün alt-ağacına karşı kontrol etmemesi (salt-okunur scope'a güvenmek)
9. **Bir authz kontrolünün kaldırılması/zayıflatılması** — `Gate::before`/middleware'in fail-open'a çevrilmesi, veya `scoped('aauth')`'un `singleton()`'a çevrilmesi (Octane state bleed)
10. **Yeni PHPStan suppression** (`@phpstan-ignore`, baseline girdisi, `ignoreErrors`, `excludePaths`) — özellikle authz/query satırında
11. **Mevcut yayınlanmış şemaya veri-temizleme adımı olmadan kısıt (FK/UNIQUE) ekleyen migration** (yetim/dup satırda çöker)
12. **Davranış değiştiren güvenlik düzeltmesinin sessizce yollanması** (CHANGELOG/UPGRADE kaydı olmadan)
13. **Yeni config flag / soyutlama** — LEAN ihlali (bir güvenlik düzeltmesinin tek breaking-change'siz aracı değilse)
14. **Güvenlik davranış değişikliğinin negatif/çapraz-org regresyon testi olmadan** yollanması
15. **Açık advisory'li bağımlılık** (`composer audit` temiz değil)

## Ortak rapor formatı

Her ajan **tam olarak** şunu üretir:

```markdown
### <emoji> [<agent-slug>] verdict: APPROVE | CHANGES_REQUESTED | BLOCK
Tek cümle özet. Checklist: X/Y geçti.

| # | Severity | Kategori | Bulgu | Konum | Öneri |
|---|----------|----------|-------|-------|-------|
| 1 | BLOCKER  | ...      | ...   | file:line | ... |

**Blockers (PR öncesi düzeltilmeli — yoksa boş):**
- ...

**Checklist:**
- [x] ID — geçti
- [ ] ID — KALDI → #1
```

**Severity ölçeği:** `BLOCKER` > `HIGH` > `MEDIUM` > `LOW` > `NIT`
**Verdict kuralı:** herhangi bir `BLOCKER` → **BLOCK** · `HIGH`/`MEDIUM` var → **CHANGES_REQUESTED** · yalnızca `LOW`/`NIT` veya temiz → **APPROVE**

## İlkeler (tüm ajanlara gömülü)

- **Yalın > akıllı.** Ajanlar aşırı-mühendisliği ve yeni config/flag/soyutlamayı **yakalar**, ödüllendirmez. Silme ve sadeleştirmeyi över.
- **Sıfır veri sızıntısı** her şeyin üstünde.
- **Salt-okunur.** Ajanlar düzeltme *uygulamaz*, yalnızca tavsiye verir.
- **"ABAC kuralsız = her şey görünür"** kasıtlı tasarımdır (additive) — ama yalnızca RBAC+OrBAC ile eşlendiğinde güvenli.
