var _search;
var _index;
var handle;

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		_search = JSON.parse(rcmail.env.help_array);
		_index = JSON.parse(rcmail.env.help_index);
	});
}

function help_search(event, object) {
	if (event.keyCode == 27) {
		object.value = "";
		document.getElementById("help-search-results").innerHTML = "";
		document.getElementById("help-search-results").style = "display: none;";
		return;
	}
	var results = {};
	if (object.value.length > 3) {
		if (handle) {
			clearTimeout(handle);
		}
		handle = setTimeout(function() {
			document.getElementById("noresulthelp").style.display = "block"; 
			var values = object.value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().split(' ');
			for (const word in _index) {
				for (const value of values) {
					if (value.length > 3) {
						if (word.indexOf(value) !== -1) {
							for (const key of _index[word]) {
								if (results[key]) {
									results[key]++;
								}
								else {
									results[key] = 1;
								}
							}
						}
					}
				}
			}
			if (Object.keys(results).length) {
				var _res = [];
				// Trier les résultats
				for (const key in results) {
					_res.push({key: key, value: results[key]});
				}
				_res.sort((a, b) => (a.value < b.value) ? 1 : -1)
				document.getElementById("help-search-results").style = "display: block;";
				document.getElementById("help-search-results").innerHTML = "";
				var i = 0;
				for (const r of _res) {
					if (i++ > 4)  {
						break;
					}
					var title = document.createElement('span');
					title.className = "title";
					title.textContent = _search[r.key].title;
					var help_url = document.createElement('a');
					help_url.href = _search[r.key].help_url;
					help_url.target = "_blank";
					help_url.title = _search[r.key].help_title;
					help_url.className = "helpurl button";
					help_url.textContent = rcmail.get_label('help search open', 'mel_help');
					var description = document.createElement('span');
					description.className = "description";
					description.innerHTML = _search[r.key].description;
					var numbers = document.createElement('span');
					numbers.className = "numbers";
					numbers.textContent = r.value === 1 ? "(1)" : "(" + r.value + ")";
					var url = document.createElement('a');
					url.href = _search[r.key].url;
					url.target = "_blank";
					url.title = _search[r.key].description;
					url.appendChild(help_url);
					url.appendChild(title);
					url.appendChild(numbers);
					url.appendChild(description);
					var result = document.createElement('div');
					result.className = "result";
					result.appendChild(url);
					document.getElementById("help-search-results").appendChild(result);
				}
			}
			else {
				document.getElementById("help-search-results").style = "display: block; text-align: center;";
				document.getElementById("help-search-results").textContent = rcmail.get_label('help search no result', 'mel_help');
			}
		}, 300);
	}
	else {
		document.getElementById("help-search-results").innerHTML = "";
		document.getElementById("help-search-results").style = "display: none;";
	}
}