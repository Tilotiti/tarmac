# Filter Component Rules

## Usage
- **ALWAYS** use the filter component (`templates/component/filters.html.twig`) for any list page with filtering
- **NEVER** create custom filter forms in templates
- Import the component using: `{% include 'component/filters.html.twig' with {form: filterForm} %}`

## Controller Pattern
```php
public function index(Request $request): Response
{
    $filterForm = $this->createForm(FilterType::class);
    $filterForm->handleRequest($request);

    $filters = [];
    if ($filterForm->isSubmitted() && $filterForm->isValid()) {
        $filters = $filterForm->getData();
        // Remove empty values
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
    }

    $items = Paginator::paginate(
        $this->repository->queryByFilters($filters),
        $request->query->getInt('page', 1),
        12
    );

    return $this->render('template.html.twig', [
        'items' => $items,
        'filterForm' => $filterForm,
    ]);
}
```

## Template Pattern
```twig
{% extends 'base.html.twig' %}

{% block body %}
    <div class="page-body">
        <div class="container-xl">
            {# Filter Component - Mobile-first with offcanvas #}
            {% include 'component/filters.html.twig' with {form: filterForm} %}

            {# List content #}
            <div class="row">
                {% for item in items %}
                    {# Item cards #}
                {% endfor %}
            </div>
        </div>
    </div>
{% endblock %}
```

## Filter Form Type Requirements
- Must extend `AbstractType`
- Use GET method: `'method' => 'GET'`
- Disable CSRF: `'csrf_protection' => false`
- All fields should be `'required' => false`
- Use appropriate field types (TextType, ChoiceType, DateType, etc.)

## Benefits
- Mobile-first design with offcanvas on mobile
- Active filter tags displayed on desktop
- Consistent UX across all filtered lists
- Automatic filter count badge
- Built-in reset functionality

