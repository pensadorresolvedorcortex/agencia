(function () {
    'use strict';

    var config = window.RSBBolaoFlags || {};
    var flags = config.flags || {};
    var matches = config.matches || [];
    var enhancedAttribute = 'data-rsb-flags-enhanced';

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function teamFlag(team) {
        var direct = flags[team];
        if (direct) {
            return direct;
        }

        var normalizedTeam = normalize(team);
        var names = Object.keys(flags);
        for (var i = 0; i < names.length; i += 1) {
            if (normalize(names[i]) === normalizedTeam) {
                return flags[names[i]];
            }
        }

        return '';
    }

    function createTeam(team) {
        var wrapper = document.createElement('span');
        wrapper.className = 'rsb-matchup__team';

        var flag = document.createElement('span');
        flag.className = 'rsb-matchup__flag';
        flag.setAttribute('aria-hidden', 'true');
        flag.textContent = teamFlag(team);

        var name = document.createElement('span');
        name.className = 'rsb-matchup__name';
        name.textContent = team;

        wrapper.append(flag, name);
        return wrapper;
    }

    function createMatchup(home, away) {
        var wrapper = document.createElement('span');
        wrapper.className = 'rsb-matchup';
        wrapper.setAttribute(enhancedAttribute, '1');
        wrapper.append(createTeam(home));

        var versus = document.createElement('span');
        versus.className = 'rsb-matchup__versus';
        versus.textContent = 'x';
        wrapper.append(versus, createTeam(away));

        return wrapper;
    }

    function matchFromText(text) {
        var compact = String(text || '').replace(/\s+/g, ' ').trim();
        for (var i = 0; i < matches.length; i += 1) {
            var match = matches[i];
            var pattern = new RegExp('\\b' + escapeRegExp(match.home) + '\\s*(?:x|×|vs\\.?|versus)\\s*' + escapeRegExp(match.away) + '\\b', 'iu');
            if (pattern.test(compact)) {
                return match;
            }
        }
        return null;
    }

    function enhanceTextNode(node) {
        if (!node.nodeValue || !matchFromText(node.nodeValue)) {
            return;
        }

        var match = matchFromText(node.nodeValue);
        var pattern = new RegExp('(' + escapeRegExp(match.home) + '\\s*(?:x|×|vs\\.?|versus)\\s*' + escapeRegExp(match.away) + ')', 'iu');
        var parts = node.nodeValue.split(pattern);
        var fragment = document.createDocumentFragment();

        parts.forEach(function (part) {
            if (!part) {
                return;
            }

            if (pattern.test(part)) {
                fragment.appendChild(createMatchup(match.home, match.away));
                return;
            }

            fragment.appendChild(document.createTextNode(part));
        });

        node.parentNode.replaceChild(fragment, node);
    }

    function enhanceSelectOptions() {
        var options = document.querySelectorAll('option:not([' + enhancedAttribute + '])');
        options.forEach(function (option) {
            var text = option.textContent || '';
            Object.keys(flags).forEach(function (team) {
                var flag = teamFlag(team);
                var pattern = new RegExp('(^|\\s)' + escapeRegExp(team) + '(?=\\s|$)', 'iu');
                if (flag && pattern.test(text) && text.indexOf(flag) === -1) {
                    text = text.replace(pattern, '$1' + flag + ' ' + team);
                }
            });
            option.textContent = text;
            option.setAttribute(enhancedAttribute, '1');
        });
    }

    function shouldSkipElement(element) {
        if (!element || element.closest('script, style, textarea, input, select, option, [contenteditable="true"], .rsb-matchup')) {
            return true;
        }

        return element.hasAttribute(enhancedAttribute);
    }

    function enhanceMatchups(root) {
        var walker = document.createTreeWalker(root || document.body, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.parentElement || shouldSkipElement(node.parentElement)) {
                    return NodeFilter.FILTER_REJECT;
                }

                return matchFromText(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });
        var nodes = [];
        var current;
        while ((current = walker.nextNode())) {
            nodes.push(current);
        }
        nodes.forEach(enhanceTextNode);
        enhanceSelectOptions();
    }

    function boot() {
        enhanceMatchups(document.body);

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        enhanceMatchups(node);
                    }
                });
            });
            enhanceSelectOptions();
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
