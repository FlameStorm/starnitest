$(function() {
  var FADE_TIME = 150; // ms
  var TYPING_TIMER_LENGTH = 400; // ms
  var COLORS = [
    '#e21400', '#91580f', '#f8a700', '#f78b00',
    '#58dc00', '#287b00', '#a8f07a', '#4ae8c4',
    '#3b88eb', '#3824aa', '#a700ff', '#d300e7'
  ];

  // Initialize varibles
  var $window = $(window);
  var $usernameInput = $('.usernameInput'); // Input for username
  var $messages = $('.messages'); // Messages area
  var $inputMessage = $('.inputMessage'); // Input message input box

  var $blocksList = $('#block_list');
  var $blocksListTbody = $blocksList.find('table > tbody');

  var $txList = $('#tx_list');
  var $txsListTbody = $txList.find('table > tbody');

  var $loginPage = $('.login.page'); // The login page
  var $chatPage = $('.chat.page'); // The chatroom page

  // Prompt for setting a username
  var username;
  var connected = false;
  var typing = false;
  var lastTypingTime;
  var $currentInput = $usernameInput.focus();

  var socket = io(location.protocol + '//'+document.domain+':' + (2020 + (location.protocol === 'https:' ? 1 : 0)));

  function addParticipantsMessage (data) {
    var message = '';
    if (data.numUsers === 1) {
      message += "there's 1 participant";
    } else {
      message += "there are " + data.numUsers + " participants";
    }
    log(message);
  }

  // Sets the client's username
  function setUsername () {
    username = cleanInput($usernameInput.val().trim());

    // If the username is valid
    if (username) {
      $loginPage.fadeOut();
      $chatPage.show();
      $loginPage.off('click');
      $currentInput = $inputMessage.focus();

      // Tell the server your username
      socket.emit('add user', username);
    }
  }

  // Sends a chat message
  function sendMessage () {
    var message = $inputMessage.val();
    // Prevent markup from being injected into the message
    message = cleanInput(message);
    // if there is a non-empty message and a socket connection
    if (message && connected) {
      $inputMessage.val('');
      addChatMessage({
        username: username,
        message: message
      });
      // tell server to execute 'new message' and send along one parameter
      socket.emit('new message', message);
    }
  }

  // Log a message
  function log (message, options) {
    var $el = $('<li>').addClass('log').text(message);
    addElementToScrollable($messages, $el, options);
  }

  // Adds the visual chat message to the message list
  function addChatMessage (data, options) {
    // Don't fade the message in if there is an 'X was typing'
    var $typingMessages = getTypingMessages(data);
    options = options || {};
    if ($typingMessages.length !== 0) {
      options.fade = false;
      $typingMessages.remove();
    }

    var $usernameDiv = $('<span class="username"/>')
      .text(data.username)
      .css('color', getUsernameColor(data.username));
    var $messageBodyDiv = $('<span class="messageBody">')
      .text(data.message);

    var typingClass = data.typing ? 'typing' : '';
    var $messageDiv = $('<li class="message"/>')
      .data('username', data.username)
      .addClass(typingClass)
      .append($usernameDiv, $messageBodyDiv);

    addElementToScrollable($messages, $messageDiv, options);
  }

  // Adds the visual chat typing message
  function addChatTyping (data) {
    data.typing = true;
    data.message = 'is typing';
    addChatMessage(data);
  }

  // Removes the visual chat typing message
  function removeChatTyping (data) {
    getTypingMessages(data).fadeOut(function () {
      $(this).remove();
    });
  }

  // Adds a message element to the messages and scrolls to the bottom
  // el - The element to add as a message
  // options.fade - If the element should fade-in (default = true)
  // options.prepend - If the element should prepend
  //   all other messages (default = false)
  function addElementToScrollable (to, el, options) {
    var $el = $(el);

    // Setup default options
    if (!options) {
      options = {};
    }
    if (typeof options.scrollElement === 'undefined') {
      options.scrollElement = to;
    }
    if (typeof options.scroll === 'undefined') {
      options.scroll = true;
    }
    if (typeof options.fade === 'undefined') {
      options.fade = true;
    }
    if (typeof options.prepend === 'undefined') {
      options.prepend = false;
    }

    // Apply options
    if (options.fade) {
      $el.hide().fadeIn(FADE_TIME);
    }

    var rowsMax = 1000;
    //var rows = to.children.length;
    Array.from(to[0].children).slice(0, -rowsMax).forEach(function(row){
      row.remove();
    });

    if (options.prepend) {
      to.prepend($el);
    } else {
      to.append($el);
    }
    if (options.scroll) {
      options.scrollElement[0].scrollTop = options.scrollElement[0].scrollHeight;
    }
  }

  // Prevents input from having injected markup
  function cleanInput (input) {
    return $('<div/>').text(input).text();
  }

  // Updates the typing event
  function updateTyping () {
    if (connected) {
      if (!typing) {
        typing = true;
        socket.emit('typing');
      }
      lastTypingTime = (new Date()).getTime();

      setTimeout(function () {
        var typingTimer = (new Date()).getTime();
        var timeDiff = typingTimer - lastTypingTime;
        if (timeDiff >= TYPING_TIMER_LENGTH && typing) {
          socket.emit('stop typing');
          typing = false;
        }
      }, TYPING_TIMER_LENGTH);
    }
  }

  // Gets the 'X is typing' messages of a user
  function getTypingMessages (data) {
    return $('.typing.message').filter(function (i) {
      return $(this).data('username') === data.username;
    });
  }

  // Gets the color of a username through our hash function
  function getUsernameColor (username) {
    // Compute hash code
    var hash = 7;
    for (var i = 0; i < username.length; i++) {
       hash = username.charCodeAt(i) + (hash << 5) - hash;
    }
    // Calculate color
    var index = Math.abs(hash % COLORS.length);
    return COLORS[index];
  }


  // Gets "2020-05-30 12:01:02 z" date string from timestamp (sec)
  function getMyDateIsoString (ts) {
    var dateIso = new Date(ts * 1000).toISOString();
    return dateIso.slice(0, 10) + ' ' + dateIso.slice(11, 19) + ' z';
  }

  // Gets "2020-05-30 12:01:02 z" date string (with html markup) from "2020-05-30 12:01:02"
  function getFormattedDateFromYmdHis (dateStr) {
    var parts = dateStr.split(' ', 2);
    return parts[0] + ' <span class="dt-time">' + parts[1] + ' z</span>';
  }

  // Gets hash without "0x" if hs, and splitted on 16-symbols groups
  function getFormattedHash (hash) {
    if (hash.slice(0, 2) === '0x') {
      hash = hash.slice(2);
    }
    var blockSize = 16;
    // alternative: '­' -> it's not minus, it's soft-hyphen
    // alternative: '-' -> it's minus (problems with FireFox word wrap)
    var blockDelimiter = '-&#8203;'; // minus with wbr (zero-width-space)
    var hashLen = hash.length;
    var pos = hashLen % blockSize;

    var result = pos ? hash.slice(0, pos) + blockDelimiter : '';
    while (true) {
      result += hash.slice(pos, pos + blockSize);
      pos += blockSize;
      if (pos >= hashLen) break;

      result += blockDelimiter;
    }
    return result;
  }


  var etheriumUnits = [
    'w', // wei
    'kw', // kwei, ada, femtoether
    'Mw', // mwei, babbage, picoether
    'Gw', // gwei, shannon, nano
    'Sz', // szabo, twei, micro
    'Fi', // finney, ewei, milli
    'E', // ether
  ];
  var etheriumDefaultUnitIndex = 6;

  // Gets etherium string value formatted with units and classes (for colors)
  function formatEtherium (value) {
    value = value - 0;
    if (!value) {
      return '<span class="eth-zero">0</span>';
    }

    var unitIndex = etheriumDefaultUnitIndex;
    while (unitIndex > 0) {
      if (value >= 10) {
        break;
      }
      value *= 1000;
      unitIndex--;
    }
    var etheriumUnit = etheriumUnits[unitIndex];
    var fractionalDigits = 2; //value >= 10.0 ? 2 : 4;
    var result = value.toFixed(fractionalDigits).split('.', 2);
    if (result[1] === '.00') {
      result[1] = result[1] + '<span class="eth-zero-frac">.00</span>';
    }
    return '<span class="eth-int-part">' + result[0] + '</span>.' + result[1] + ' '
      + '<span class="eth-unit eth-' + etheriumUnit + '">' + etheriumUnit + '</span>';
  }

  // Adds rows accordingly to new blocks to Block's table
  function addNewBlocks (data) {
    var blocks = data.blocks;
    for (var blockIndex in blocks) {
      if (!blocks.hasOwnProperty(blockIndex)) continue;

      var block = blocks[blockIndex];
      var html = '';
      html += '<tr>';

      html += '<td class="text-fixed">';
      html += '<b>' + block.number + '</b><br />';
      html += '<span class="text-small text-teal eth-hash">' + getFormattedHash(block.hash) + '</span>';
      html += '</td>';

      html += '<td class="text-center">';
      html += block.tx_count;
      html += '</td>';

      html += '<td class="text-center">';
      html += block.gas_used + '<div class="text-small">of <span class="text-green">' + block.gas_limit + '</span></div>';
      html += '</td>';

      html += '<td class="text-center dt-multiline">';
      html += getFormattedDateFromYmdHis(block.ts);
      html += '</td>';

      html += '</tr>';

      var $row = $(html);

      addElementToScrollable($blocksListTbody, $row, {scrollElement: $blocksList, fade: false});
    }
  }

  // Adds rows accordingly to new transactions to Transactions's table
  function addNewTransactions (data) {
    /*
     <td class="text-fixed">
     <b>123456789 - 12</b><br />
     <span class="text-small text-teal">f2b469ad09fa59c4-796588d75c4006d4-e0063c1e05be525b-4138ac8dc69627b5</span></td>
     <td class="text-center text-nowrap">12.345 Gwei</td>
    /**/
    var txs = data.transactions;
    for (var txIndex in txs) {
      if (!txs.hasOwnProperty(txIndex)) continue;

      var tx = txs[txIndex];
      var html = '';
      html += '<tr>';

      html += '<td class="text-fixed">';
      html += '<b>' + tx.block_number + ' - ' + tx.transaction_index + '</b><br />';
      html += '<span class="text-small text-teal eth-hash">' + getFormattedHash(tx.hash) + '</span>';
      html += '</td>';

      html += '<td class="text-center">';
      html += '<div class="text-nowrap eth-align">';
      html += formatEtherium(tx.gas * tx.gas_price);
      html += '</div>';
      html += '<div class="text-small text-nowrap">';
      html += ' = <span class="text-green">' + tx.gas + '</span>';
      html += ' * <span class="text-green">' + formatEtherium(tx.gas_price) + '</span>';
      html += '</div>';
      html += '</td>';

      html += '<td class="text-center text-nowrap eth-align" title="' + tx.value + ' E">';
      html += formatEtherium(tx.value);
      html += '</td>';

      html += '</tr>';

      var $row = $(html);

      addElementToScrollable($txsListTbody, $row, {scrollElement: $txList, fade: false});
    }
  }



  // Keyboard events

  $window.keydown(function (event) {
    // Auto-focus the current input when a key is typed
    if (!(event.ctrlKey || event.metaKey || event.altKey)) {
      $currentInput.focus();
    }
    // When the client hits ENTER on their keyboard
    if (event.which === 13) {
      if (username) {
        sendMessage();
        socket.emit('stop typing');
        typing = false;
      } else {
        setUsername();
      }
    }
  });

  $inputMessage.on('input', function() {
    updateTyping();
  });



  // Click events

  // Focus input when clicking anywhere on login page
  $loginPage.click(function () {
    $currentInput.focus();
  });

  // Focus input when clicking on the message input's border
  $inputMessage.click(function () {
    $inputMessage.focus();
  });



  // Socket events

  // Whenever the server emits 'login', log the login message
  socket.on('login', function (data) {
    connected = true;
    // Display the welcome message
    var message = "Welcome to Socket.IO Chat – ";
    log(message, {
      prepend: true
    });
    addParticipantsMessage(data);
  });

  // Whenever the server emits 'new message', update the chat body
  socket.on('new message', function (data) {
    addChatMessage(data);
  });

  // Whenever the server emits 'user joined', log it in the chat body
  socket.on('user joined', function (data) {
    log(data.username + ' joined');
    addParticipantsMessage(data);
  });

  // Whenever the server emits 'user left', log it in the chat body
  socket.on('user left', function (data) {
    log(data.username + ' left');
    addParticipantsMessage(data);
    removeChatTyping(data);
  });

  // Whenever the server emits 'typing', show the typing message
  socket.on('typing', function (data) {
    addChatTyping(data);
  });

  // Whenever the server emits 'stop typing', kill the typing message
  socket.on('stop typing', function (data) {
    removeChatTyping(data);
  });


  // On new blocks broadcast
  socket.on('new blocks', function (data) {
    addNewBlocks(data);
  });

  // On new transactions broadcast
  socket.on('new transactions', function (data) {
    addNewTransactions(data);
  });

});
