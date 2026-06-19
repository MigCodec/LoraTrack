# Integración SAP S/4HANA

El conector inicial consume Product Master mediante OData (`API_PRODUCT_SRV`). La URL base y ruta son configurables para admitir diferentes deployments.

## Normalización

| SAP | LoraTrack |
| --- | --- |
| `Product` | referencia externa y código SKU |
| `ProductDescription` | nombre del producto/SKU |
| `BaseUnit` | unidad base |
| `ProductType` | atributo |
| `ProductGroup` | atributo |

Los códigos se guardan como texto y no pierden ceros iniciales. Cada referencia es única por conector e identificador SAP. Un checksum evita escrituras innecesarias.

Product Master no representa existencias por centro o almacén. Esa capacidad requiere una API y sincronizador separados.

Referencia: <https://api.sap.com/api/API_PRODUCT_SRV/overview>
