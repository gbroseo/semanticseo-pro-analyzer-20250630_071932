const analysisId = <?= json_encode($analysisId) ?>;
    const csrfToken = <?= json_encode($csrfToken) ?>;

    function escapeHtml(str) {
        return str.replace(/[&<>"'`=\/]/g, s => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;',
            '`': '&#x60;',
            '=': '&#x3D;'
        })[s]);
    }

    async function fetchResults(filters = {}) {
        const params = new URLSearchParams({ analysis_id: analysisId });
        Object.entries(filters).forEach(([k, v]) => v && params.append(k, v));
        try {
            const res = await fetch(`api/results.php?${params}`, {
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrfToken }
            });
            if (!res.ok) throw new Error(`Server responded with status ${res.status}`);
            const data = await res.json();
            document.getElementById('errorBanner').style.display = 'none';
            return data;
        } catch (error) {
            console.error('Error fetching results:', error);
            const banner = document.getElementById('errorBanner');
            banner.textContent = 'An error occurred while loading results. Please try again.';
            banner.style.display = 'block';
            return [];
        }
    }

    function showResults(data) {
        const tbody = document.querySelector('#resultsTable tbody');
        tbody.innerHTML = '';
        data.forEach(item => {
            const tr = document.createElement('tr');

            const tdText = document.createElement('td');
            tdText.textContent = item.text;
            tr.appendChild(tdText);

            const tdType = document.createElement('td');
            tdType.textContent = item.entity_type;
            tr.appendChild(tdType);

            const tdRel = document.createElement('td');
            tdRel.textContent = item.relevance_score.toString();
            tr.appendChild(tdRel);

            const tdSent = document.createElement('td');
            tdSent.textContent = item.sentiment_score !== null ? item.sentiment_score.toString() : '-';
            tr.appendChild(tdSent);

            tbody.appendChild(tr);
        });
    }

    function filterResults() {
        const filters = {
            text: document.getElementById('filterText').value.trim(),
            entity_type: document.getElementById('filterEntityType').value
        };
        fetchResults(filters).then(showResults);
    }

    document.getElementById('applyFilters').addEventListener('click', filterResults);
    document.getElementById('filterText').addEventListener('keyup', e => {
        if (e.key === 'Enter') filterResults();
    });

    document.getElementById('exportCsv').addEventListener('click', () => {
        const filters = {
            text: document.getElementById('filterText').value.trim(),
            entity_type: document.getElementById('filterEntityType').value
        };
        const params = new URLSearchParams({ analysis_id: analysisId });
        if (filters.text) params.append('text', filters.text);
        if (filters.entity_type) params.append('entity_type', filters.entity_type);
        window.location.href = `export.php?${params}`;
    });

    document.addEventListener('DOMContentLoaded', () => {
        fetchResults().then(showResults);
    });
</script>
</body>
</html>