  </main>
  <script>
    // Auto-focus URL input field
    document.addEventListener("DOMContentLoaded", function() {
      const urlField = document.getElementById('url');
      if (urlField) urlField.focus();
    });

    // Dark Mode Toggle with PicoCSS
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme');

    // Initialize theme from localStorage or system preference
    if (savedTheme) {
      html.setAttribute('data-theme', savedTheme);
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      html.setAttribute('data-theme', 'dark');
    }

    // Toggle theme function
    function toggleTheme() {
      const currentTheme = html.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
    }

    // Job status using Server-Sent Events (SSE)
    let eventSource = null;

    function connectSSE() {
      // Close existing connection if any
      if (eventSource) {
        eventSource.close();
      }

      eventSource = new EventSource('/api/jobs/stream');

      eventSource.onmessage = function(e) {
        try {
          const data = JSON.parse(e.data);

          if (data.type === 'connected') {
            console.log('SSE connected');
            return;
          }

          if (data.type === 'update') {
            updateJobDisplay(data);
          }
        } catch (err) {
          console.error('Error parsing SSE data:', err);
        }
      };

      eventSource.onerror = function(e) {
        console.error('SSE error, reconnecting...', e);
        eventSource.close();
        // Reconnect after 5 seconds
        setTimeout(connectSSE, 5000);
      };
    }

    function updateJobDisplay(data) {
      const jobCount = document.getElementById('job-count');
      const jobList = document.getElementById('job-list');

      if (!jobCount || !jobList) return;

      jobCount.textContent = data.active_count;

      jobList.innerHTML = '';

      if (data.active.length === 0) {
        jobList.innerHTML = '<li>No active jobs</li>';
        return;
      }

      // Show active jobs with progress bars
      data.active.forEach(job => {
        const li = document.createElement('li');
        const progress = Math.round(job.progress || 0);
        const urlPreview = job.url.substring(0, 40);

        li.innerHTML = `
          <strong>${urlPreview}${job.url.length > 40 ? '...' : ''}</strong><br>
          <progress value="${progress}" max="100"></progress> ${progress}%<br>
          <small>${job.status}</small>
        `;
        jobList.appendChild(li);
      });
    }

    // Initialize SSE connection when page loads
    if (document.getElementById('job-count')) {
      connectSSE();

      // Cleanup on page unload
      window.addEventListener('beforeunload', () => {
        if (eventSource) {
          eventSource.close();
        }
      });
    }

    // Format fetching functionality
    let availableFormats = { video: [], audio: [] };

    function fetchFormats() {
      const urlInput = document.getElementById('url');
      const url = urlInput.value.trim();

      if (!url) {
        alert('Please enter a URL first');
        return;
      }

      const btn = document.getElementById('fetch-formats');
      btn.disabled = true;
      btn.textContent = 'Fetching...';

      fetch('/api/formats', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url })
      })
        .then(r => r.json())
        .then(data => {
          if (data.error) {
            alert('Failed to fetch formats: ' + data.error);
            return;
          }

          availableFormats.video = data.video_formats || [];
          availableFormats.audio = data.audio_formats || [];

          populateFormatDropdown();

          document.getElementById('format-label').style.display = 'block';
          btn.textContent = 'Fetch Formats';
          btn.disabled = false;
        })
        .catch(err => {
          alert('Failed to fetch formats: ' + err.message);
          btn.textContent = 'Fetch Formats';
          btn.disabled = false;
        });
    }

    function toggleFormatType() {
      populateFormatDropdown();
    }

    function populateFormatDropdown() {
      const isAudio = document.getElementById('audioCheck').checked;
      const select = document.getElementById('format-select');

      const formats = isAudio ? availableFormats.audio : availableFormats.video;

      select.innerHTML = '<option value="">Auto (based on quality)</option>';

      formats.forEach(f => {
        const resolution = f.height ? f.height + 'p' : '';
        const note = f.format_note || '';
        const ext = f.ext || '';
        const label = `${f.format_id} - ${ext}${note ? ' - ' + note : ''}${resolution ? ' ' + resolution : ''}`.trim();
        select.innerHTML += `<option value="${f.format_id}">${label}</option>`;
      });
    }
  </script>
</body>
</html>
