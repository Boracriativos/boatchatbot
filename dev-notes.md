# Action

It's an action pushed by the model to execute a function on the client-side. Best is to use Code Engine, attach a Callable Action with a JS as Target, and attach it to the chatbot. The action will be automatically executed on the client-side.

## Type: 'function'

data: { name, args }

# Shortcut

A shortcut is basically a button that will be pushed by the server-side or directly in JS via the MwaiAPI (setShortcuts). Each shortcut has a type and data.

The `args` contains a lot of information about the request, the chatbot, etc. It also contains a `step` attribute, which can be set to `init` if the chatbot has just been initialized, or `reply` if it's a reply.

## Type: 'message'

The label will be displayed in a button in the chatbot. When clicked, the message will be sent to the chatbot. The appareance of the button can be customized through its variant and icon.

data: { label, message, icon, variant (success, warning, danger, info) }

## Examples

With JS:

```
MwaiAPI.chatbots.forEach(chatbot => {
  chatbot.setShortcuts([
    {
      type: 'message',
      data: { 
        label: 'Hi', 
        message: 'Hello, nice to meet you, what can you do for me?', 
        variant: 'info', 
        icon: null 
      }
    },
    {
      type: 'message',
      data: { 
        label: 'Bye', 
        message: 'Goodbye, see you soon!', 
        variant: 'danger', 
        icon: null 
      }
    }
  ]);
});
```

With PHP:

```
add_filter( "mwai_chatbot_shortcuts", function ( $shortcuts, $args ) {
  // The block will be added only if the word 'shortcut' is detected in the query or reply
  if ( strpos( $args['reply'], 'shortcut' ) === false && strpos( $args['newMessage'], 'shortcut' ) === false) {
    return $blocks;
  }
  $shortcuts[] = [
    'type' => 'message',
    'data' => [
      'label' => 'Hi', 
      'message' => 'Hello, nice to meet you, what can you do for me?', 
      'variant' => 'info', 
      'icon' => null 
    ]
  ];
  $shortcuts[] = [
    'type' => 'message',
    'data' => [
      'label' => 'Bye', 
      'message' => 'Goodbye, see you soon!', 
      'variant' => 'danger', 
      'icon' => null 
    ]
  ];
  return $shortcuts;
}, 10, 2);
```

# Block

It's a some HTML code that can be pushed by the server-side or directly in JS via the MwaiAPI (setBlocks). A block can be blocking or non-blocking. For instance, it could force an user to enter some data before continuing.

## Type: 'content'

data { html, script }.

## Examples

With JS:

```
MwaiAPI.getChatbot().setBlocks([
  {
    type: 'content',
    data: { 
      html: `
        <p>The chatbot will be blocked until you type your name.</p>
        <form id="userForm">
          <label for="name">Name:</label>
          <input type="text" id="name" name="name" required><br><br>
          <button type="submit">Submit</button>
        </form>
      `,
      script: `
        const chatbot = MwaiAPI.getChatbot();
        chatbot.lock();
        document.getElementById('userForm').addEventListener('submit', function(event) {
          event.preventDefault();
          const name = document.getElementById('name').value;
          alert("Hi " + name + "!");
          chatbot.unlock();
        });
      `,
    }
  }
]);
```

With PHP:

```
add_filter( "mwai_chatbot_blocks", function ( $blocks, $args ) {
  // The block will be added only if the word 'block' is detected in the query or reply
  if ( strpos( $args['reply'], 'block' ) === false && strpos( $args['newMessage'], 'block' ) === false) {
    return $blocks;
  }
  $blocks[] = [
    'type' => 'content',
    'data' => [
      'html' => '<div>
          <p>The chatbot will be blocked until you type your name.</p>
          <form id="userForm">
          <label for="name">Name:</label>
          <input type="text" id="name" name="name" required><br><br>
          <button type="submit">Submit</button>
        </form>
        </div>',
      'script' => '
        const chatbot = MwaiAPI.getChatbot("' . $args['botId'] . '");
        chatbot.lock();
        document.getElementById("userForm").addEventListener("submit", function(event) {
          event.preventDefault();
          const name = document.getElementById("name").value;
          alert("Hi " + name + "!");
          chatbot.unlock();
          chatbot.setBlocks([{ type: "content", data: { html: "Thank you!" } }]);
        });
      '
    ]
  ];
  return $blocks;
}, 10, 2);
```